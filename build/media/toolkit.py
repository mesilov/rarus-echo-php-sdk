"""Pinned, offline-verifiable compact media source and payload contracts (stdlib only)."""
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import platform
import re
import shutil
import stat
import subprocess
import tarfile
import tempfile
import time
import urllib.request

if __package__:
    from .console import configure_build_console, utf8_environment
else:
    from console import configure_build_console, utf8_environment

configure_build_console()

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
CONFIG = json.loads((HERE / 'config.json').read_text(encoding='utf-8'))
DIGEST = re.compile(r'[0-9a-f]{64}')


def digest(path):
    with Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def profile_sha(config=None):
    return hashlib.sha256(json.dumps((config or CONFIG)['configure_args'],
                                    separators=(',', ':')).encode()).hexdigest()


def identity():
    return {'ffmpeg_version': CONFIG['ffmpeg_version'], 'source_sha256': CONFIG['source_sha256'],
            'profile_sha256': profile_sha()}


def recipe_inputs():
    paths = [*HERE.rglob('*'), *(ROOT / 'build/licenses').rglob('*'),
             ROOT / 'build/tests/linkage-check.py', *(ROOT / 'tests/Fixtures/Media').rglob('*')]
    return sorted(p for p in paths if p.is_file() and '__pycache__' not in p.parts and p.suffix != '.pyc')


def recipe_sha():
    return hashlib.sha256(b''.join(p.relative_to(ROOT).as_posix().encode() + b'\0' + p.read_bytes()
                                   for p in recipe_inputs())).hexdigest()


def configured_components(kind):
    flag = next(arg for arg in CONFIG['configure_args'] if arg.startswith('--enable-' + kind + '='))
    return set(flag.split('=', 1)[1].split(','))


def validate_codecs(text, kind):
    entries = [(letter, name) for letter, name in re.findall(r'^ ([VAS])[A-Z.]{5} (\S+)', text, re.MULTILINE)
               if name != '=']
    if (not entries or any(letter != 'A' for letter, name in entries)
            or {name for letter, name in entries} != configured_components(kind)):
        raise ValueError('Unexpected or missing audio codec capabilities: ' + kind)


def cache_key(target, compiler, config=None, recipe_sha=None):
    config = config or CONFIG
    return hashlib.sha256(json.dumps({'source': config['source_sha256'], 'profile': profile_sha(config),
        'target': target, 'compiler': compiler, 'target_inputs': config['targets'][target],
        'recipe': recipe_sha or globals()['recipe_sha']()}, sort_keys=True).encode()).hexdigest()


def verify_source(path):
    if Path(path).stat().st_size > CONFIG['maximum_source_bytes'] or digest(path) != CONFIG['source_sha256']:
        raise ValueError('FFmpeg source checksum differs from the reviewed release')


def acquire_source(destination):
    destination = Path(destination)
    if destination.exists():
        verify_source(destination)
        return destination
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary = destination.with_suffix(destination.suffix + '.partial')
    try:
        # Every read has a socket deadline; the overall transfer has a strict deadline and byte cap.
        deadline, size = time.monotonic() + 240, 0
        with urllib.request.urlopen(CONFIG['source_url'], timeout=30) as response, temporary.open('xb') as output:
            if not response.url.startswith('https://'):
                raise ValueError('Source redirects must retain HTTPS')
            while True:
                if time.monotonic() >= deadline:
                    raise TimeoutError('Source fetch deadline exceeded')
                data = response.read1(65536)
                if not data:
                    break
                size += len(data)
                if size > CONFIG['maximum_source_bytes']:
                    raise ValueError('Source download exceeds limit')
                output.write(data)
        verify_source(temporary)
        temporary.replace(destination)
    finally:
        temporary.unlink(missing_ok=True)
    return destination


def extract_source(source, destination):
    destination = Path(destination)
    prefix, seen, size = 'ffmpeg-' + CONFIG['ffmpeg_version'], set(), 0
    destination.mkdir(parents=True, exist_ok=True)
    with tarfile.open(source, 'r|*') as archive:
        for member in archive:
            name = member.name.rstrip('/')
            parts = PurePosixPath(name).parts
            if (not name or name.startswith('/') or '\\' in name or ':' in name
                    or any(ord(char) < 32 for char in name) or '..' in parts
                    or '.' in parts or parts[0] != prefix or name.casefold() in seen
                    or not (member.isfile() or member.isdir())):
                raise ValueError('Unsafe or duplicate FFmpeg source member: ' + name)
            seen.add(name.casefold())
            size += member.size
            if size > CONFIG['maximum_extracted_bytes'] or len(seen) > 30000:
                raise ValueError('Source extraction exceeds limit')
            output = destination.joinpath(*parts)
            if member.isdir():
                output.mkdir(parents=True, exist_ok=True)
            else:
                output.parent.mkdir(parents=True, exist_ok=True)
                with archive.extractfile(member) as src, output.open('xb') as dst:
                    shutil.copyfileobj(src, dst)
                output.chmod(0o755 if member.mode & 0o111 else 0o644)
    return destination / prefix


def manifest_paths(target):
    suffix = '.exe' if target == 'windows-amd64' else ''
    return ['media-build-info.json', 'media-source.tar.xz',
            'libexec/ffmpeg' + suffix, 'libexec/ffprobe' + suffix]


def write_metadata(root, target, configure_args, compiler, key):
    root = Path(root)
    paths = manifest_paths(target)
    info = {'schema_version': 1, **identity(), 'target': target, 'configure_args': configure_args,
            'compiler': compiler, 'minimum_os': CONFIG['targets'][target]['minimum_os'],
            'cache_key': key, 'binaries': {name: {'path': paths[i + 2], 'sha256': digest(root / paths[i + 2])}
                for i, name in enumerate(['ffmpeg', 'ffprobe'])},
            'source_package': {'path': paths[1], 'sha256': digest(root / paths[1])}}
    (root / paths[0]).write_text(json.dumps(info, indent=2, sort_keys=True) + '\n', encoding='utf-8', newline='\n')
    (root / 'media-SHA256SUMS').write_text(''.join(digest(root / name) + '  ' + name + '\n'
                                               for name in paths), encoding='utf-8', newline='\n')
    return info


def verify_payload(root, target, expected_key=None):
    root = Path(root)
    paths = manifest_paths(target)
    # Guard directories before hashing: a symlinked parent or FIFO must never be followed.
    for tree in ['libexec', 'media-licenses']:
        directory = root / tree
        if directory.is_symlink() or not directory.is_dir() or getattr(directory.lstat(), 'st_file_attributes', 0) & 0x400:
            raise ValueError('Media payload has an unsafe symlink/non-directory tree')
        for member in directory.rglob('*'):
            status = member.lstat()
            mode = status.st_mode
            if not (stat.S_ISREG(mode) or stat.S_ISDIR(mode)) or getattr(status, 'st_file_attributes', 0) & 0x400:
                raise ValueError('Media payload must contain safe regular files/directories')
    for name in [*paths, 'media-SHA256SUMS']:
        status = (root / name).lstat()
        if not stat.S_ISREG(status.st_mode) or getattr(status, 'st_file_attributes', 0) & 0x400:
            raise ValueError('Media payload must contain regular files')
    info = json.loads((root / paths[0]).read_text(encoding='utf-8'))
    if info.get('target') != target:
        raise ValueError('Media target identity differs')
    if info.get('schema_version') != 1 or any(info.get(k) != v for k, v in identity().items()):
        raise ValueError('Media source/profile identity differs')
    if (not isinstance(info.get('configure_args'), list) or info['configure_args'][:len(CONFIG['configure_args'])] != CONFIG['configure_args']
            or not info.get('compiler') or not info.get('minimum_os')
            or (expected_key and info.get('cache_key') != expected_key)):
        raise ValueError('Incomplete media provenance or stale cache')
    expected = {name: digest(root / name) for name in paths}
    canonical = ''.join(expected[name] + '  ' + name + '\n' for name in paths).encode('ascii')
    if (root / 'media-SHA256SUMS').read_bytes() != canonical:
        raise ValueError('Media checksum manifest or file hash differs; expected canonical LF records')
    for name, path in zip(['ffmpeg', 'ffprobe'], paths[2:]):
        if info.get('binaries', {}).get(name) != {'path': path, 'sha256': expected[path]}:
            raise ValueError('Media binary hash differs')
    if info.get('source_package') != {'path': paths[1], 'sha256': expected[paths[1]]}:
        raise ValueError('Media source package hash differs')
    for name in [*paths, 'media-SHA256SUMS']:
        if (root / name).is_symlink() or not (root / name).is_file():
            raise ValueError('Media payload must be regular files')
    licenses = root / 'media-licenses'
    if not licenses.is_dir() or not any(p.is_file() for p in licenses.rglob('*')):
        raise ValueError('Media licenses missing')
    return info


def verify_source_package(root):
    expected_source = 'ffmpeg-' + CONFIG['ffmpeg_version'] + '.tar.xz'
    required = {expected_source, 'recipe/build/media/config.json', 'recipe/build/media/toolkit.py', 'recipe/build/media/build.py',
                'recipe/build/media/accept.py', 'recipe/build/media/console.py', 'recipe/build/media/README.md', 'recipe/build/media/upstream-source.tar.xz.asc',
                'recipe/build/media/upstream-signing-key.asc', 'recipe/build/tests/linkage-check.py',
                'recipe/tests/Fixtures/Media/manifest.json', 'effective-build.json'}
    seen, total = set(), 0
    with tarfile.open(Path(root) / 'media-source.tar.xz', 'r|xz') as archive:
        for member in archive:
            if (not member.isfile() or member.name.startswith('/') or '\\' in member.name or ':' in member.name
                    or any(ord(char) < 32 for char in member.name)
                    or any(p in {'', '.', '..'} for p in member.name.split('/'))
                    or member.name.casefold() in seen):
                raise ValueError('Unsafe corresponding source package member')
            seen.add(member.name.casefold())
            total += member.size
            if total > CONFIG['maximum_source_bytes'] + 4 * 1024 * 1024:
                raise ValueError('Corresponding source package exceeds limit')
            with archive.extractfile(member) as stream:
                if member.name == expected_source:
                    actual = hashlib.file_digest(stream, 'sha256').hexdigest()
                    if actual != CONFIG['source_sha256']:
                        raise ValueError('Corresponding upstream source checksum differs')
                elif member.name == 'recipe/build/media/config.json':
                    config = json.load(stream)
                    if config['source_sha256'] != CONFIG['source_sha256'] or profile_sha(config) != profile_sha():
                        raise ValueError('Corresponding source recipe profile differs')
    if not {name.casefold() for name in required}.issubset(seen):
        raise ValueError('Corresponding source/recipe/signature materials missing')


def run(args, cwd=None, env=None, timeout=900):
    configure_build_console()
    env = utf8_environment(env)
    print('+ ' + ' '.join(map(str, args)), flush=True)
    result = subprocess.run(list(map(str, args)), cwd=cwd, env=env, timeout=timeout,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                            encoding='utf-8', errors='backslashreplace')
    if result.returncode:
        print(result.stdout, flush=True)
        result.check_returncode()
    return result.stdout


def source_package(source, root, configure_args, compiler, key):
    # The original verified upstream archive is retained byte for byte, plus the complete build recipe.
    with tempfile.TemporaryDirectory(prefix='echo-media-source-') as temp:
        staging = Path(temp)
        shutil.copy2(source, staging / ('ffmpeg-' + CONFIG['ffmpeg_version'] + '.tar.xz'))
        for path in recipe_inputs():
            target = staging / 'recipe' / path.relative_to(ROOT)
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, target)
        (staging / 'effective-build.json').write_text(json.dumps({'target_configure_args': configure_args,
            'compiler': compiler, 'cache_key': key, **identity()}, indent=2) + '\n', encoding='utf-8', newline='\n')
        with tarfile.open(Path(root) / 'media-source.tar.xz', 'w:xz', preset=6) as archive:
            for path in sorted(staging.rglob('*')):
                if path.is_file():
                    member = archive.gettarinfo(str(path), path.relative_to(staging).as_posix())
                    member.uid = member.gid = member.mtime = 0
                    member.uname = member.gname = ''
                    member.mode = 0o644
                    with path.open('rb') as stream:
                        archive.addfile(member, stream)
