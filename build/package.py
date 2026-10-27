#!/usr/bin/env python3
"""Build locked production PHARs and native standalone executables."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import sys

from runtime import ROOT, CONFIG, run, composer, toolchain, build_runtime, extensions_for
from source import verify_checkout, validate_sha
from release_assets import TARGETS, pack


def identity(source_sha, version, target):
    sha = verify_checkout(ROOT, source_sha) if (ROOT / ".git").exists() else validate_sha(source_sha or "")
    if version and not re.fullmatch(r"v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", version):
        raise ValueError("Release version must be a version tag")
    return {"version": version or "dev-" + sha, "source_sha": sha, "target": target,
            "php_version": CONFIG["php_version"], "static_php_cli_ref": CONFIG["static_php_cli_ref"],
            "micro_ref": CONFIG["micro_ref"], "extensions": extensions_for(target),
            "composer_lock_sha256": hashlib.sha256((ROOT / CONFIG["dependency_lock"]).read_bytes()).hexdigest(),
            "toolchain_patches": {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest()
                                  for name in CONFIG["toolchain_patches"]}}


def stage(work, info, env):
    app = work / "app"
    if app.exists():
        shutil.rmtree(app)
    app.mkdir(parents=True)
    for relative in CONFIG["application_paths"]:
        source, dest = ROOT / relative, app / relative
        dest.parent.mkdir(parents=True, exist_ok=True)
        if source.is_dir():
            shutil.copytree(source, dest, symlinks=True)
        else:
            shutil.copy2(source, dest)
    shutil.copy2(ROOT / CONFIG["dependency_lock"], app / "composer.lock")
    (app / "build-info.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8")
    composer(["install", "--no-dev", "--prefer-dist", "--no-interaction", "--no-progress",
              "--no-plugins", "--no-scripts", "--classmap-authoritative"], app, env)
    composer(["check-platform-reqs", "--no-dev"], app, env)
    return app


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("target", choices=["phar"] + TARGETS)
    parser.add_argument("--source-sha", default=os.environ.get("ECHO_SOURCE_SHA"))
    parser.add_argument("--version", default=os.environ.get("ECHO_RELEASE_VERSION"))
    args = parser.parse_args()
    info = identity(args.source_sha, args.version, args.target)
    if args.target != "phar":
        expected = {"linux-amd64": ("Linux", "x86_64"), "linux-arm64": ("Linux", "aarch64"),
                    "macos-arm64": ("Darwin", "arm64"), "windows-amd64": ("Windows", "AMD64")}[args.target]
        if (platform.system(), platform.machine()) != expected:
            raise RuntimeError("Native target mismatch: " + str(expected))
    work = ROOT / "runtime" / ("package-" + args.target)
    dist = ROOT / "dist" / args.target
    work.mkdir(parents=True, exist_ok=True)
    if dist.exists():
        shutil.rmtree(dist)
    dist.mkdir(parents=True)
    env = os.environ.copy()
    env["COMPOSER_CACHE_DIR"] = str(work / "composer-cache")
    env["COMPOSER_ROOT_VERSION"] = args.version or "dev-dev"
    env["ECHO_PAYLOAD_STRATEGY"] = "temporary-phar" if os.name == "nt" else "in-place"
    app = stage(work, info, env)
    for name, mode in [("rarus-echo", "application"), ("runtime-probe", "probe")]:
        run(["php", "-d", "phar.readonly=0", ROOT / "build/package-phar.php", app, dist / (name + ".phar"), mode], env=env)
    run(["php", dist / "rarus-echo.phar", "--version", "--no-ansi"], env=env)
    (dist / "build-info.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8")
    if args.target == "phar":
        return
    spc = toolchain(work, env)
    build_runtime(spc, env, args.target)
    suffix = ".exe" if os.name == "nt" else ""
    for name in ["rarus-echo", "runtime-probe"]:
        binary, payload = dist / (name + suffix), dist / (name + ".phar")
        run(["php", "bin/spc", "micro:combine", payload, "-O", binary], spc, env)
        binary.chmod(0o755)
        if args.target == "macos-arm64":
            from macos import finalize
            finalize(binary, payload)
    licenses = spc / "buildroot/license"
    if not licenses.is_dir():
        licenses = spc / "buildroot/licenses"
    if not licenses.is_dir():
        raise RuntimeError("Runtime license dump missing")
    shutil.copytree(licenses, dist / "runtime-licenses")
    shutil.copy2(spc / "source/php-src/sapi/micro/LICENSE", dist / "runtime-licenses/phpmicro-LICENSE.txt")
    if args.target.startswith("linux-"):
        shutil.copy2(spc / "source/musl-1.2.5/COPYRIGHT", dist / "runtime-licenses/musl-COPYRIGHT.txt")
        for name in ["GCC-COPYING3.txt", "GCC-COPYING.RUNTIME.txt"]:
            shutil.copy2(ROOT / "build/licenses" / name, dist / "runtime-licenses" / name)
    shutil.copy2(spc / "downloads/.lock.json", dist / "runtime-sources.json")
    for name in ["THIRD-PARTY-NOTICES.md", "BITRIX24-CLI-LICENSE.txt", "YIIPRESS-LICENSE.txt"]:
        shutil.copy2(ROOT / "build" / name, dist / name)
    shutil.copy2(ROOT / "LICENSE", dist / "RARUS-ECHO-LICENSE.txt")
    archive = pack(dist, args.target, ROOT / "dist/assets")
    run([sys.executable, ROOT / "build/tests/smoke.py", archive,
         "--probe", dist / ("runtime-probe" + suffix),
         "--version", info["version"], "--source-sha", info["source_sha"]], env=env)


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, ValueError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
