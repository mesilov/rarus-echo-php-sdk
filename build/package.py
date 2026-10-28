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
import tempfile

from media.console import configure_build_console

configure_build_console()

from runtime import ROOT, CONFIG, run, composer, toolchain, build_runtime, extensions_for
from source import verify_checkout, validate_sha
from release_assets import TARGETS, pack
from media import toolkit as media_toolkit
from compress import provenance, compress_micro, pack_macos, export_notices, write_report, verify_report


def identity(source_sha, version, target):
    sha = verify_checkout(ROOT, source_sha) if (ROOT / ".git").exists() else validate_sha(source_sha or "")
    if version and not re.fullmatch(r"v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", version):
        raise ValueError("Release version must be a version tag")
    return {"version": version or "dev-" + sha, "source_sha": sha, "target": target,
            "php_version": CONFIG["php_version"], "static_php_cli_ref": CONFIG["static_php_cli_ref"],
            "micro_ref": CONFIG["micro_ref"], "extensions": extensions_for(target),
            "executable_compression": provenance(target),
            **({"media": media_toolkit.identity()} if target != "phar" else {}),
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
    (app / "build-info.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8", newline="\n")
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
    run(["php", "-d", "phar.readonly=0", ROOT / "build/tests/phar-cleanup-check.php"], env=env)
    env["ECHO_LAUNCHER"] = "wrappe" if args.target == "macos-arm64" else "none"
    run(["php", ROOT / "build/tests/launcher-path-check.php"], env=env)
    app = stage(work, info, env)
    for name, mode in [("rarus-echo", "application"), ("runtime-probe", "probe")]:
        run(["php", "-d", "phar.readonly=0", ROOT / "build/package-phar.php", app, dist / (name + ".phar"), mode], env=env)
    run(["php", dist / "rarus-echo.phar", "--version", "--no-ansi"], env=env)
    (dist / "build-info.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8", newline="\n")
    if args.target == "phar":
        return
    media_stage = work / "media"
    run([sys.executable, ROOT / "build/media/build.py", args.target,
         "--destination", media_stage], env=env)
    run([sys.executable, ROOT / "build/media/accept.py", media_stage,
         "--target", args.target, "--linkage"], env=env)
    for name in ["libexec", "media-licenses", "media-build-info.json", "media-SHA256SUMS", "media-source.tar.xz"]:
        source = media_stage / name
        if source.is_dir():
            shutil.copytree(source, dist / name)
        else:
            shutil.copy2(source, dist / name)
    spc = toolchain(work, env)
    build_runtime(spc, env, args.target)
    suffix = ".exe" if os.name == "nt" else ""
    raw = dist / "uncompressed"
    raw.mkdir()
    compressed_runtime = compress_micro(args.target, work, spc / "buildroot/bin/micro.sfx") if args.target != "macos-arm64" else None
    for name in ["rarus-echo", "runtime-probe"]:
        payload = dist / (name + ".phar")
        original, binary = raw / (name + suffix), dist / (name + suffix)
        run(["php", "-d", "memory_limit=1G", "bin/spc", "micro:combine", payload, "-O", original], spc, env)
        original.chmod(0o755)
        if args.target == "macos-arm64":
            from macos import finalize
            finalize(original, payload)
            shutil.copy2(original, binary)
            pack_macos(work, binary, env)
        else:
            run(["php", "-d", "memory_limit=1G", "bin/spc", "micro:combine", payload,
                 "--with-micro", compressed_runtime, "-O", binary], spc, env)
            if not binary.read_bytes().endswith(payload.read_bytes()):
                raise RuntimeError("Compressed executable changed embedded PHAR bytes")
        binary.chmod(0o755)
        for candidate in [original, binary]:
            run([sys.executable, ROOT / "build/tests/linkage-check.py", candidate], env=env)
    export_notices(args.target, work, dist / "compression-licenses")
    write_report(dist, args.target)
    verify_report(dist)
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
    shutil.copy2(ROOT / "build/THIRD-PARTY-NOTICES.md", dist / "THIRD-PARTY-NOTICES.md")
    shutil.copy2(ROOT / "LICENSE", dist / "RARUS-ECHO-LICENSE.txt")
    # The same full native fixture set accepts original and packed executables.
    sys.path.insert(0, str(ROOT / "build/tests"))
    from smoke import smoke
    with tempfile.TemporaryDirectory(prefix="echo-original-") as temp:
        original_package = Path(temp)
        shutil.copytree(dist, original_package, dirs_exist_ok=True, ignore=shutil.ignore_patterns("uncompressed"))
        for name in ["rarus-echo", "runtime-probe"]:
            shutil.copy2(raw / (name + suffix), original_package / (name + suffix))
        smoke(original_package, info["version"], info["source_sha"], original_package / ("runtime-probe" + suffix))
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
