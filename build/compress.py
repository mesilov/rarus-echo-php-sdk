#!/usr/bin/env python3
"""Compress native executables without changing the cached PHP runtime."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import struct
import tarfile
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parent.parent
CONFIG = json.loads((ROOT / "build/compression.json").read_text())


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def provenance(target):
    if target == "phar":
        return {"tool": "none"}
    if target == "macos-arm64":
        config = CONFIG["wrappe"]
        return {"tool": "wrappe", "version": config["version"], "strategy": "whole-executable",
                "compression": config["compression"], "rust_toolchain": config["rust_toolchain"],
                "crate_sha256": config["crate_sha256"],
                "runner_build_patch_sha256": digest(ROOT / config["runner_build_patch"]),
                "launcher_patch_sha256": digest(ROOT / config["launcher_patch"])}
    config = CONFIG["upx"]
    return {"tool": "upx", "version": config["version"], "strategy": "micro-before-phar",
            "level": config["level"], "archive_sha256": config["targets"][target]["sha256"]}


def checked_download(url, destination, expected):
    destination = Path(destination)
    destination.parent.mkdir(parents=True, exist_ok=True)
    if not destination.exists():
        temporary = destination.with_suffix(destination.suffix + ".download")
        try:
            urllib.request.urlretrieve(url, temporary)
            if digest(temporary) != expected:
                raise RuntimeError("Compressor archive SHA256 mismatch")
            temporary.replace(destination)
        finally:
            temporary.unlink(missing_ok=True)
    if digest(destination) != expected:
        raise RuntimeError("Cached compressor archive SHA256 mismatch")
    return destination


def check_version(tool, expected, env=None):
    result = subprocess.check_output([str(tool), "--version"], text=True, env=env)
    name = "wrappe" if Path(tool).name.startswith("wrappe") else "upx"
    if not re.search(r"(?im)^" + name + r"\s+" + re.escape(expected) + r"(?:\s|$)", result):
        raise RuntimeError("Unexpected compressor version: " + result.strip())


def install_upx(target, work):
    config = CONFIG["upx"]
    source = config["targets"][target]
    root = Path(work) / "packers"
    archive = checked_download(source["url"], root / Path(source["url"]).name, source["sha256"])
    executable = "upx.exe" if target.startswith("windows") else "upx"
    tool = root / executable
    notices = root / "upx-notices"
    notices.mkdir(exist_ok=True)
    if archive.suffix == ".zip":
        with zipfile.ZipFile(archive) as packed:
            members = [(entry.filename, packed.read(entry)) for entry in packed.infolist() if not entry.is_dir()]
    else:
        with tarfile.open(archive) as packed:
            members = [(entry.name, packed.extractfile(entry).read()) for entry in packed.getmembers() if entry.isfile()]
    binaries = [data for name, data in members if Path(name).name == executable]
    if len(binaries) != 1:
        raise RuntimeError("UPX archive does not contain exactly one executable")
    tool.write_bytes(binaries[0])
    tool.chmod(0o755)
    for name, data in members:
        if Path(name).name.upper().startswith(("LICENSE", "COPYING")):
            (notices / Path(name).name).write_bytes(data)
    if not any(notices.iterdir()):
        raise RuntimeError("UPX license notices are missing")
    check_version(tool, config["version"])
    return tool


def prepare_wrappe_source(archive, root):
    source = Path(root) / "source"
    if source.exists():
        shutil.rmtree(source)
    source.mkdir()
    with tarfile.open(archive) as crate:
        for member in crate.getmembers():
            destination = source / member.name
            if not destination.resolve().is_relative_to(source.resolve()) or not (member.isfile() or member.isdir()):
                raise RuntimeError("Unsafe wrappe source archive member")
            if member.isdir():
                destination.mkdir(parents=True, exist_ok=True)
            else:
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.write_bytes(crate.extractfile(member).read())
    source = source / ("wrappe-" + CONFIG["wrappe"]["version"])
    for key in ["runner_build_patch", "launcher_patch"]:
        patch = json.loads((ROOT / CONFIG["wrappe"][key]).read_text())
        path = source / patch["file"]
        content = path.read_text()
        if content.count(patch["before"]) != 1:
            raise RuntimeError("wrappe build patch differs from pinned source")
        path.write_text(content.replace(patch["before"], patch["after"]), encoding="utf-8", newline="\n")
    return source


def install_wrappe(work, env):
    config = CONFIG["wrappe"]
    root = Path(work) / "packers"
    archive = checked_download("https://crates.io/api/v1/crates/wrappe/" + config["version"] + "/download",
                     root / "wrappe.crate", config["crate_sha256"])
    tool = root / "bin/wrappe"
    cargo_env = env.copy()
    cargo_env["CARGO_HOME"] = str(root / "cargo-home")
    stamp = root / "wrappe-build-info.json"
    expected = provenance("macos-arm64")
    installed = json.loads(stamp.read_text()) if stamp.is_file() else None
    rebuild = not tool.is_file() or not installed or installed["provenance"] != expected
    if not rebuild and installed["tool_sha256"] != digest(tool):
        raise RuntimeError("Cached wrappe executable SHA256 mismatch")
    if rebuild:
        if not shutil.which("cargo"):
            raise RuntimeError("Rust/cargo is required to build the macOS compressor")
        source = prepare_wrappe_source(archive, root)
        subprocess.run(["cargo", "+" + config["rust_toolchain"], "install", "--locked", "--force",
                        "--path", str(source), "--root", str(root)], env=cargo_env, check=True)
    check_version(tool, config["version"], cargo_env)
    notices = root / "wrappe-notices"
    if rebuild and notices.exists():
        shutil.rmtree(notices)
    if not notices.is_dir():
        collect_wrappe_notices(root, notices)
    if rebuild:
        stamp.write_text(json.dumps({"provenance": expected, "tool_sha256": digest(tool)}, indent=2) + "\n")
    return tool


def require_reduction(original, compressed):
    if not Path(compressed).is_file() or not 0 < Path(compressed).stat().st_size < Path(original).stat().st_size:
        raise RuntimeError("Compression did not produce a smaller executable")


def finalize_upx_elf(binary):
    """Follow SPC's Linux micro fix: discard data beyond PT_LOAD file bounds."""
    binary = Path(binary)
    data = binary.read_bytes()
    if len(data) < 64 or data[:6] != b'\x7fELF\x02\x01':
        raise ValueError('Expected a 64-bit little-endian UPX ELF')
    offset = struct.unpack_from('<Q', data, 32)[0]
    entry_size, count = struct.unpack_from('<HH', data, 54)
    if entry_size != 56 or not count or offset < 64 or offset + count * entry_size > len(data):
        raise ValueError('Invalid UPX ELF program headers')
    end = 0
    for i in range(count):
        entry = offset + i * entry_size
        if struct.unpack_from('<I', data, entry)[0] != 1:
            continue
        file_offset, file_size = struct.unpack_from('<Q', data, entry + 8)[0], struct.unpack_from('<Q', data, entry + 32)[0]
        if file_offset + file_size > len(data):
            raise ValueError('UPX ELF load segment exceeds file bounds')
        end = max(end, file_offset + file_size)
    if not end:
        raise ValueError('UPX ELF has no file-backed load segment')
    binary.write_bytes(data[:end])


def compress_micro(target, work, runtime):
    tool = install_upx(target, work)
    result = Path(work) / "packers/micro.sfx"
    shutil.copy2(runtime, result)
    subprocess.run([str(tool), CONFIG["upx"]["level"], str(result)], check=True)
    subprocess.run([str(tool), "--test", str(result)], check=True)
    if target.startswith("linux-"):
        finalize_upx_elf(result)
    require_reduction(runtime, result)
    return result


def pack_macos(work, binary, env):
    from macos import finalize_overlay
    tool = install_wrappe(work, env)
    binary = Path(binary)
    root = Path(work) / ("wrappe-input-" + binary.name)
    if root.exists():
        shutil.rmtree(root)
    root.mkdir()
    shutil.copy2(binary, root / binary.name)
    packed = binary.with_name(binary.name + ".packed")
    packed.unlink(missing_ok=True)
    subprocess.run([str(tool), "--compression", str(CONFIG["wrappe"]["compression"]),
                    "--show-information", "none", str(root), binary.name, str(packed)], env=env, check=True)
    packed.chmod(0o755)
    finalize_overlay(packed)
    require_reduction(binary, packed)
    packed.replace(binary)


def collect_wrappe_notices(root, destination):
    # Preserve notices beside the cached tool; both builds use scoped CARGO_HOME.
    registries = [Path(root) / "cargo-home/registry/src"]
    count = 0
    for registry in registries:
        for crate in registry.glob("*/*"):
            for source in crate.rglob("*"):
                if source.is_file() and source.name.upper().startswith(("LICENSE", "COPYING", "NOTICE")):
                    dest = Path(destination) / crate.name / source.relative_to(crate)
                    dest.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(source, dest)
                    count += 1
    if not count:
        raise RuntimeError("wrappe dependency notices are missing")


def export_notices(target, work, destination):
    destination = Path(destination)
    destination.mkdir(exist_ok=True)
    if target == "macos-arm64":
        shutil.copy2(ROOT / "build/licenses/wrappe-LICENSE.txt", destination / "wrappe-LICENSE.txt")
        notices = Path(work) / "packers/wrappe-notices"
        if not notices.is_dir() or not any(notices.rglob("*")):
            raise RuntimeError("wrappe dependency notices are missing")
        shutil.copytree(notices, destination / "wrappe-dependencies", dirs_exist_ok=True)
    else:
        for source in (Path(work) / "packers/upx-notices").iterdir():
            shutil.copy2(source, destination / ("upx-" + source.name))


def write_report(dist, target):
    dist = Path(dist)
    report = {"target": target, "compressor": provenance(target), "executables": {}}
    for name in ["rarus-echo", "runtime-probe"]:
        name += ".exe" if target.startswith("windows") else ""
        original, final = dist / "uncompressed" / name, dist / name
        require_reduction(original, final)
        report["executables"][name] = {
            "original_bytes": original.stat().st_size, "compressed_bytes": final.stat().st_size,
            "original_sha256": digest(original), "compressed_sha256": digest(final)}
    (dist / "compression-info.json").write_text(json.dumps(report, indent=2) + "\n")
    print(json.dumps(report, indent=2))
    return report


def verify_report(dist):
    dist = Path(dist)
    report = json.loads((dist / "compression-info.json").read_text())
    if report["compressor"] != provenance(report["target"]):
        raise RuntimeError("Compression provenance differs from the pinned configuration")
    info = json.loads((dist / "build-info.json").read_text())
    if info["executable_compression"] != report["compressor"]:
        raise RuntimeError("Embedded build identity and compression report differ")
    expected = {"rarus-echo", "runtime-probe"}
    if report["target"].startswith("windows"):
        expected = {name + ".exe" for name in expected}
    if set(report["executables"]) != expected:
        raise RuntimeError("Compression report executable set differs")
    for name, item in report["executables"].items():
        original, final = dist / "uncompressed" / name, dist / name
        require_reduction(original, final)
        for prefix, path in [("original", original), ("compressed", final)]:
            if path.stat().st_size != item[prefix + "_bytes"] or digest(path) != item[prefix + "_sha256"]:
                raise RuntimeError("Compression size/SHA256 differs from actual bytes: " + name)
    print("Verified compressor provenance, exact hashes and size reduction")
    return report


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dist", type=Path)
    verify_report(parser.parse_args().dist)
