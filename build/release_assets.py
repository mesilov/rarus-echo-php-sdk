#!/usr/bin/env python3
"""Create and verify the complete release archive/checksum set."""
import argparse
import hashlib
import json
from pathlib import Path
import tarfile
import zipfile

TARGETS = ["linux-amd64", "linux-arm64", "macos-arm64", "windows-amd64"]
REQUIRED = ["build-info.json", "runtime-sources.json", "THIRD-PARTY-NOTICES.md",
            "RARUS-ECHO-LICENSE.txt", "BITRIX24-CLI-LICENSE.txt", "YIIPRESS-LICENSE.txt",
            "runtime-licenses"]


def archive_name(target):
    return "rarus-echo-" + target + (".zip" if target.startswith("windows") else ".tar.gz")


def executable(target):
    return "rarus-echo.exe" if target.startswith("windows") else "rarus-echo"


def pack(root, target, destination):
    root, destination = Path(root), Path(destination)
    destination.mkdir(parents=True, exist_ok=True)
    files = []
    for name in REQUIRED + [executable(target)]:
        path = root / name
        if not path.exists():
            raise FileNotFoundError(path)
        members = list(path.rglob("*")) if path.is_dir() else [path]
        if not members:
            raise ValueError("Empty required directory: " + name)
        for member in members:
            if member.is_symlink():
                raise ValueError("Symlinks are not distributable")
            if member.is_file():
                files.append(member)
    output = destination / archive_name(target)
    if target.startswith("windows"):
        with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
            for path in sorted(files):
                archive.write(path, path.relative_to(root))
    else:
        with tarfile.open(output, "w:gz") as archive:
            for path in sorted(files):
                info = archive.gettarinfo(str(path), path.relative_to(root).as_posix())
                info.mode = 0o755 if path.name == executable(target) else 0o644
                info.uid = info.gid = 0
                info.uname = info.gname = ""
                with path.open("rb") as stream:
                    archive.addfile(info, stream)
    return output


def inspect_archive(path, target, version, sha):
    if target.startswith("windows"):
        with zipfile.ZipFile(path) as archive:
            names = archive.namelist()
            metadata = archive.read("build-info.json")
    else:
        with tarfile.open(path) as archive:
            if any(not member.isfile() for member in archive):
                raise ValueError("Archive must contain regular files")
            if archive.getmember("rarus-echo").mode != 0o755:
                raise ValueError("Missing executable permission")
            names = archive.getnames()
            metadata = archive.extractfile("build-info.json").read()
    required = set(REQUIRED) - {"runtime-licenses"}
    required.add(executable(target))
    if not required.issubset(names) or not any(n.startswith("runtime-licenses/") for n in names):
        raise ValueError("Incomplete archive contents")
    allowed = required | {"runtime-licenses"}
    if len(names) != len(set(names)) or any(
            name.startswith("/") or "\\" in name or ".." in Path(name).parts
            or name.split("/")[0] not in allowed for name in names):
        raise ValueError("Unexpected archive contents")
    info = json.loads(metadata)
    if (info.get("version"), info.get("source_sha"), info.get("target")) != (version, sha, target):
        raise ValueError("Archive build identity differs")
    return info


def validate_set(directory, version, sha):
    directory = Path(directory)
    expected = {archive_name(t) for t in TARGETS}
    actual = {p.name for p in directory.iterdir()}
    if actual - {"SHA256SUMS"} != expected:
        raise ValueError("Expected exactly four platform archives")
    infos = [inspect_archive(directory / archive_name(t), t, version, sha) for t in TARGETS]
    for key in ["php_version", "static_php_cli_ref", "micro_ref", "composer_lock_sha256", "toolchain_patches"]:
        if any(info.get(key) != infos[0].get(key) for info in infos):
            raise ValueError("Platform build identity differs: " + key)


def write_checksums(directory, version, sha):
    directory = Path(directory)
    validate_set(directory, version, sha)
    content = "".join(hashlib.sha256((directory / archive_name(t)).read_bytes()).hexdigest()
                      + "  " + archive_name(t) + "\n" for t in TARGETS)
    (directory / "SHA256SUMS").write_text(content, encoding="utf-8")


def verify_assets(directory, version, sha):
    directory = Path(directory)
    lines = (directory / "SHA256SUMS").read_text().splitlines()
    expected = {archive_name(t) for t in TARGETS}
    records = [line.split("  ") for line in lines]
    if any(len(row) != 2 for row in records) or len(records) != 4 or {row[1] for row in records} != expected:
        raise ValueError("Invalid checksum manifest")
    for digest, name in records:
        if hashlib.sha256((directory / name).read_bytes()).hexdigest() != digest:
            raise ValueError("Archive checksum differs: " + name)
    validate_set(directory, version, sha)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    parser.add_argument("--version", required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--verify-only", action="store_true")
    args = parser.parse_args()
    if not args.verify_only:
        write_checksums(args.directory, args.version, args.source_sha)
    verify_assets(args.directory, args.version, args.source_sha)
    print("Verified all four archives and SHA256SUMS")


if __name__ == "__main__":
    main()
