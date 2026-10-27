#!/usr/bin/env python3
"""Pinned PHP micro compiler, independent of application staging and smoke code."""
import argparse
import base64
import hashlib
import io
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess
import sys
import tarfile
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
CONFIG = json.loads((ROOT / "build/config.json").read_text())


def extensions_for(target):
    return CONFIG['extensions'] + CONFIG['target_extensions'][target]


def sanitize_logs(directory, env):
    token = env.get("GITHUB_TOKEN")
    if not token:
        return
    secrets = [token]
    if env.get("GITHUB_USER"):
        secrets.append(base64.b64encode((env["GITHUB_USER"] + ":" + token).encode()).decode())
    for log in (Path(directory) / "log").glob("*.log"):
        content = log.read_text(encoding="utf-8", errors="replace")
        for secret in secrets:
            content = content.replace(secret, "[redacted]")
        log.write_text(content, encoding="utf-8")


def run(args, cwd=ROOT, env=None):
    print("+ " + " ".join(map(str, args)), flush=True)
    try:
        subprocess.run(list(map(str, args)), cwd=cwd, env=env, check=True)
    except subprocess.CalledProcessError:
        sanitize_logs(cwd, env or os.environ)
        for name in ["spc.shell.log", "spc.output.log"]:
            log = Path(cwd) / "log" / name
            if log.is_file():
                print("Compiler diagnostics: " + name, file=sys.stderr)
                print("\n".join(log.read_text(encoding="utf-8", errors="replace").splitlines()[-100:]), file=sys.stderr)
        raise


def composer(args, cwd, env):
    # Windows Composer is a batch launcher; call its PHP payload directly.
    executable = shutil.which("composer")
    if not executable:
        raise RuntimeError("Composer is required on the build host")
    if os.name == "nt":
        phar = Path(executable).with_name("composer.phar")
        if not phar.is_file():
            raise RuntimeError("composer.phar must be beside the Composer launcher")
        return run(["php", phar, *args], cwd, env)
    return run([executable, *args], cwd, env)


def toolchain(work, env):
    ref = CONFIG["static_php_cli_ref"]
    spc = work / ("static-php-cli-" + ref)
    if not spc.exists():
        archive = work / "static-php-cli.tar.gz"
        urllib.request.urlretrieve("https://codeload.github.com/crazywhalecc/static-php-cli/tar.gz/" + ref, archive)
        unpack = work / "unpack"
        unpack.mkdir(exist_ok=True)
        with tarfile.open(archive) as tar:
            for member in tar.getmembers():
                destination = (unpack / member.name).resolve()
                if not (member.isfile() or member.isdir()) or not destination.is_relative_to(unpack.resolve()):
                    raise RuntimeError("Unsafe member in toolchain archive")
            # Debian's Python 3.11 lacks extraction filters; all members were validated above.
            tar.extractall(unpack)
        (unpack / ("static-php-cli-" + ref)).rename(spc)
        shutil.rmtree(unpack)
        archive.unlink()
    source = json.loads((spc / "config/source.json").read_text())
    # SPC's git downloader treats rev as a branch and otherwise falls back to master.
    # Use the commit archive and disable alternate sources to enforce the actual pin.
    source["micro"].update({"type": "url",
                            "url": "https://codeload.github.com/static-php/phpmicro/tar.gz/" + CONFIG["micro_ref"],
                            "filename": "phpmicro-" + CONFIG["micro_ref"] + ".tar.gz"})
    source["micro"].pop("rev", None)
    (spc / "config/source.json").write_text(json.dumps(source, indent=4) + "\n")
    for name in CONFIG["toolchain_patches"]:
        patch = json.loads((ROOT / name).read_text())
        if patch.get("phase") == "micro-source":
            continue
        path = spc / patch["file"]
        content = path.read_text()
        if content.count(patch["before"]) == 1:
            path.write_text(content.replace(patch["before"], patch["after"]))
        elif content.count(patch["after"]) != 1:
            raise RuntimeError("Toolchain patch does not match the pinned source: " + name)
    composer(["install", "--no-dev", "--no-interaction", "--no-progress", "--classmap-authoritative"], spc, env)
    return spc


def patch_micro_archive(spc, target):
    patches = [json.loads((ROOT / name).read_text()) for name in CONFIG["toolchain_patches"]]
    patches = [patch for patch in patches if patch.get("phase") == "micro-source"
               and target in patch.get("targets", ["windows-amd64"])]
    if not patches:
        return
    lock_path = spc / "downloads/.lock.json"
    lock = json.loads(lock_path.read_text())
    archive = spc / "downloads" / lock["micro"]["filename"]
    original_hash = hashlib.sha1(archive.read_bytes()).hexdigest()
    changed = set()
    with tarfile.open(archive, "r:gz") as source, tarfile.open(archive.with_suffix(".patched"), "w:gz") as dest:
        for member in source.getmembers():
            data = source.extractfile(member).read() if member.isfile() else None
            for patch in patches:
                if member.isfile() and member.name.split("/", 1)[-1] == patch["file"]:
                    content = data.decode("utf-8").replace("\r\n", "\n")
                    for replacement in patch["replacements"]:
                        if content.count(replacement["before"]) != 1:
                            raise RuntimeError("Micro patch does not match the pinned source: " + patch["file"])
                        content = content.replace(replacement["before"], replacement["after"])
                    data = content.encode("utf-8")
                    member.size = len(data)
                    changed.add(patch["file"])
            dest.addfile(member, io.BytesIO(data) if data is not None else None)
    if changed != {patch["file"] for patch in patches}:
        raise RuntimeError("Micro patch target is missing")
    archive.with_suffix(".patched").replace(archive)
    lock["micro"]["rarus-echo_original_archive_sha1"] = original_hash
    lock["micro"]["hash"] = hashlib.sha1(archive.read_bytes()).hexdigest()
    lock_path.write_text(json.dumps(lock, indent=4) + "\n")


def build_runtime(spc, env, target):
    if target.startswith("linux-"):
        env["SPC_TOOLCHAIN"] = "SPC\\toolchain\\MuslToolchain"
        env["SPC_TARGET"] = "aarch64-linux-musl" if target.endswith("arm64") else "x86_64-linux-musl"
    runtime_key = hashlib.sha256((ROOT / "build/config.json").read_bytes()
                                 + Path(__file__).read_bytes() + target.encode()
                                 + b"".join((ROOT / name).read_bytes() for name in CONFIG["toolchain_patches"])).hexdigest()
    marker = spc / "rarus-echo-runtime-key"
    micro = spc / "buildroot/bin/micro.sfx"
    if not micro.exists() or not marker.exists() or marker.read_text() != runtime_key:
        run(["php", "bin/spc", "download", "--for-extensions=" + ",".join(extensions_for(target)),
             "--with-php=" + CONFIG["php_version"], "--without-suggestions", "--no-alt",
             "--retry=2", "--ignore-cache-sources=micro"], spc, env)
        lock = json.loads((spc / "downloads/.lock.json").read_text())
        if lock["micro"].get("filename") != "phpmicro-" + CONFIG["micro_ref"] + ".tar.gz":
            raise RuntimeError("PHP micro source differs from the pinned commit")
        patch_micro_archive(spc, target)
        run(["php", "bin/spc", "doctor", "--auto-fix"], spc, env)
        run(["php", "bin/spc", "build", ",".join(extensions_for(target)), "--build-micro", "--with-micro-fake-cli"], spc, env)
        sanitize_logs(spc, env)
        marker.write_text(runtime_key)



def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('target', choices=['linux-amd64', 'linux-arm64', 'macos-arm64', 'windows-amd64'])
    args = parser.parse_args()
    expected = {'linux-amd64': ('Linux', 'x86_64'), 'linux-arm64': ('Linux', 'aarch64'), 'macos-arm64': ('Darwin', 'arm64'),
                'windows-amd64': ('Windows', 'AMD64')}[args.target]
    if (platform.system(), platform.machine()) != expected:
        raise RuntimeError('Native target mismatch: ' + str(expected))
    work = ROOT / 'runtime' / ('package-' + args.target)
    work.mkdir(parents=True, exist_ok=True)
    env = os.environ.copy()
    env['COMPOSER_CACHE_DIR'] = str(work / 'composer-cache')
    build_runtime(toolchain(work, env), env, args.target)


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, ValueError, subprocess.CalledProcessError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
