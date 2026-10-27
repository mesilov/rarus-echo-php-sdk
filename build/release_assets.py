#!/usr/bin/env python3
"""Create and verify the complete release archive/checksum set."""
import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import re
import stat
import tarfile
import tempfile
import zipfile

from media import toolkit

TARGETS = ['linux-amd64', 'linux-arm64', 'macos-arm64', 'windows-amd64']
REQUIRED = ['build-info.json', 'runtime-sources.json', 'THIRD-PARTY-NOTICES.md',
            'RARUS-ECHO-LICENSE.txt', 'BITRIX24-CLI-LICENSE.txt', 'YIIPRESS-LICENSE.txt',
            'runtime-licenses', 'libexec', 'media-build-info.json', 'media-SHA256SUMS',
            'media-source.tar.xz', 'media-licenses']
DIRECTORIES = {'runtime-licenses', 'media-licenses'}
LEGACY_REQUIRED = ['build-info.json', 'runtime-sources.json', 'THIRD-PARTY-NOTICES.md',
                   'RARUS-ECHO-LICENSE.txt', 'BITRIX24-CLI-LICENSE.txt', 'YIIPRESS-LICENSE.txt',
                   'runtime-licenses']
# Frozen, actually published pre-media payload. No missing-file detection selects this exception.
LEGACY_MEDIALESS_RELEASES = {'0.5.0-rc-1': 'e359975527bd483707dc39d23599545a77c1620d'}


def validate_legacy_identity(version, sha):
    if LEGACY_MEDIALESS_RELEASES.get(version) != sha:
        raise ValueError('No approved published legacy media contract for this version/source identity')


def archive_name(target):
    return 'rarus-echo-' + target + ('.zip' if target.startswith('windows') else '.tar.gz')


def executable(target):
    return 'rarus-echo.exe' if target.startswith('windows') else 'rarus-echo'


def executables(target, require_media=True):
    return {executable(target), *toolkit.manifest_paths(target)[2:]} if require_media else {executable(target)}


def allowed_name(name, target, require_media=True):
    if (not name or name.startswith('/') or '\\' in name or ':' in name
            or any(part in {'', '.', '..'} for part in name.split('/'))
            or any(ord(char) < 32 for char in name)):
        return False
    required = REQUIRED if require_media else LEGACY_REQUIRED
    directories = DIRECTORIES if require_media else {'runtime-licenses'}
    if name in executables(target, require_media) or name in set(required) - directories - {'libexec'}:
        return True
    return len(name.split('/')) > 1 and name.split('/')[0] in directories


def _pack_files(files, root, output, target):
    if target.startswith('windows'):
        with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for path in files:
                name = path.relative_to(root).as_posix()
                info = zipfile.ZipInfo(name, (1980, 1, 1, 0, 0, 0))
                info.create_system = 3
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = (stat.S_IFREG | (0o755 if name in executables(target) else 0o644)) << 16
                archive.writestr(info, path.read_bytes())
    else:
        with tarfile.open(output, 'w:gz', compresslevel=9) as archive:
            for path in files:
                name = path.relative_to(root).as_posix()
                info = archive.gettarinfo(str(path), name)
                info.mode = 0o755 if name in executables(target) else 0o644
                info.uid = info.gid = info.mtime = 0
                info.uname = info.gname = ''
                with path.open('rb') as stream:
                    archive.addfile(info, stream)


def pack(root, target, destination):
    root, destination = Path(root), Path(destination)
    toolkit.verify_payload(root, target)
    destination.mkdir(parents=True, exist_ok=True)
    files = []
    for name in REQUIRED + [executable(target)]:
        path = root / name
        if path.is_symlink() or not path.exists():
            raise FileNotFoundError(path)
        members = list(path.rglob('*')) if path.is_dir() else [path]
        if not members:
            raise ValueError('Empty required directory: ' + name)
        for member in members:
            if member.is_symlink():
                raise ValueError('Symlinks are not distributable')
            if member.is_file():
                if not allowed_name(member.relative_to(root).as_posix(), target):
                    raise ValueError('Unexpected archive contents')
                files.append(member)
    files = sorted(files)
    output = destination / archive_name(target)
    _pack_files(files, root, output, target)
    info = json.loads((root / 'build-info.json').read_text())
    inspect_archive(output, target, info['version'], info['source_sha'])
    # A same-CLI no-media archive isolates all media bytes, including source and notices.
    with tempfile.TemporaryDirectory(prefix='echo-size-') as temp:
        baseline = Path(temp) / archive_name(target)
        base_files = [p for p in files if not p.relative_to(root).as_posix().startswith(('media-', 'libexec/'))]
        _pack_files(base_files, root, baseline, target)
        increment = output.stat().st_size - baseline.stat().st_size
        report = {'baseline': '7f41160 archive layout, same CLI/runtime with media payload omitted',
                  'target': target, 'baseline_compressed_bytes': baseline.stat().st_size,
                  'complete_compressed_bytes': output.stat().st_size,
                  'compressed_media_increment_bytes': increment,
                  'media_uncompressed_bytes': sum(p.stat().st_size for p in files if p not in base_files),
                  'maximum_compressed_media_increment_bytes': toolkit.CONFIG['maximum_compressed_increment']}
        (root / 'media-size-report.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        if increment > toolkit.CONFIG['maximum_compressed_increment']:
            raise ValueError('Complete compressed media increment exceeds 25 MiB')
    (destination / 'SHA256SUMS').write_text(toolkit.digest(output) + '  ' + output.name + '\n', encoding='utf-8')
    return output


def inspect_archive(path, target, version, sha, *, require_media=True):
    if not require_media:
        validate_legacy_identity(version, sha)
    records, directories, components = {}, set(), {}
    archive = zipfile.ZipFile(path) if target.startswith('windows') else tarfile.open(path)
    with archive:
        members = archive.infolist() if target.startswith('windows') else archive.getmembers()
        for member in members:
            name = member.filename if target.startswith('windows') else member.name
            key = name.casefold()
            parts = name.split('/')
            for index in range(1, len(parts) + 1):
                component = '/'.join(parts[:index])
                folded = component.casefold()
                if folded in components and components[folded] != component:
                    raise ValueError('Archive path component case collision: ' + name)
                components[folded] = component
                if index < len(parts):
                    if folded in records:
                        raise ValueError('Archive file-parent collision: ' + name)
                    directories.add(folded)
                elif folded in directories:
                    raise ValueError('Archive parent-file collision: ' + name)
            if not allowed_name(name, target, require_media) or key in records:
                raise ValueError('Unsafe, unexpected or duplicate archive contents: ' + name)
            if target.startswith('windows'):
                mode = member.external_attr >> 16
                if member.is_dir() or (stat.S_IFMT(mode) not in {0, stat.S_IFREG}):
                    raise ValueError('Archive must contain regular files')
                permissions = {0o755 if name in executables(target, require_media) else 0o644}
                if not require_media and member.create_system == 0:
                    # The frozen Windows release used ZipFile.write over NTFS:
                    # its signed-off payload has regular 0666 data / 0777 executable modes.
                    permissions.add(0o777 if name == executable(target) else 0o666)
                if mode and stat.S_IMODE(mode) not in permissions:
                    raise ValueError('Invalid archive permission: ' + name)
                stream = archive.open(member)
            else:
                if not member.isfile():
                    raise ValueError('Archive must contain regular files')
                if member.mode != (0o755 if name in executables(target, require_media) else 0o644):
                    raise ValueError('Invalid archive executable/data permission: ' + name)
                stream = archive.extractfile(member)
            with stream:
                data = stream.read()
            records[key] = (name, hashlib.sha256(data).hexdigest(), data if name.endswith('.json')
                            or name == 'media-SHA256SUMS' else None)
    names = {row[0] for row in records.values()}
    directories = DIRECTORIES if require_media else {'runtime-licenses'}
    required = (set(REQUIRED if require_media else LEGACY_REQUIRED) - directories - {'libexec'}) | executables(target, require_media)
    if not required.issubset(names) or any(not any(n.startswith(d + '/') for n in names) for d in directories):
        raise ValueError('Incomplete archive contents')
    info = json.loads(records['build-info.json'][2])
    if (info.get('version'), info.get('source_sha'), info.get('target')) != (version, sha, target):
        raise ValueError('Archive build identity differs')
    if not require_media:
        if 'media' in info:
            raise ValueError('Legacy archive cannot declare a media build identity')
        return info
    media = json.loads(records['media-build-info.json'][2])
    common = toolkit.identity()
    if (media.get('target') != target or media.get('schema_version') != 1
            or any(media.get(k) != v for k, v in common.items()) or info.get('media') != common
            or not isinstance(media.get('configure_args'), list) or not media['configure_args']
            or not media.get('compiler') or not media.get('minimum_os')):
        raise ValueError('Media build identity/provenance differs')
    paths = toolkit.manifest_paths(target)
    for name, member_path in zip(['ffmpeg', 'ffprobe'], paths[2:]):
        if media.get('binaries', {}).get(name) != {'path': member_path, 'sha256': records[member_path][1]}:
            raise ValueError('Media binary hash differs')
    if media.get('source_package') != {'path': paths[1], 'sha256': records[paths[1]][1]}:
        raise ValueError('Media source package hash differs')
    expected = ''.join(records[p][1] + '  ' + p + '\n' for p in paths).encode()
    if records['media-sha256sums'][2] != expected:
        raise ValueError('Media checksum manifest differs')
    return info


def validate_set(directory, version, sha):
    directory = Path(directory)
    expected = {archive_name(t) for t in TARGETS}
    actual = {p.name for p in directory.iterdir()}
    if actual - {'SHA256SUMS'} != expected:
        raise ValueError('Expected exactly four platform archives')
    infos = [inspect_archive(directory / archive_name(t), t, version, sha) for t in TARGETS]
    for key in ['php_version', 'static_php_cli_ref', 'micro_ref', 'composer_lock_sha256', 'toolchain_patches', 'media']:
        if any(info.get(key) != infos[0].get(key) for info in infos):
            raise ValueError('Platform build identity differs: ' + key)


def write_checksums(directory, version, sha):
    directory = Path(directory)
    validate_set(directory, version, sha)
    content = ''.join(toolkit.digest(directory / archive_name(t)) + '  ' + archive_name(t) + '\n' for t in TARGETS)
    (directory / 'SHA256SUMS').write_text(content, encoding='utf-8')


def verify_assets(directory, version, sha):
    directory = Path(directory)
    lines = (directory / 'SHA256SUMS').read_text().splitlines()
    expected = {archive_name(t) for t in TARGETS}
    records = [line.split('  ') for line in lines]
    if (any(len(row) != 2 or not re.fullmatch(r'[0-9a-f]{64}', row[0]) for row in records)
            or len(records) != 4 or {row[1] for row in records} != expected):
        raise ValueError('Invalid checksum manifest')
    for digest, name in records:
        if toolkit.digest(directory / name) != digest:
            raise ValueError('Archive checksum differs: ' + name)
    validate_set(directory, version, sha)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory', type=Path)
    parser.add_argument('--version', required=True)
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--verify-only', action='store_true')
    args = parser.parse_args()
    if not args.verify_only:
        write_checksums(args.directory, args.version, args.source_sha)
    verify_assets(args.directory, args.version, args.source_sha)
    print('Verified all four complete archives and SHA256SUMS')


if __name__ == '__main__':
    main()
