#!/usr/bin/env python3
"""Compile the pinned audio-only FFmpeg pair on the selected native target."""
import argparse
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess
import sys
import tarfile
import tempfile

from toolkit import (CONFIG, HERE, ROOT, acquire_source, cache_key, extract_source, identity,
                     run, source_package, verify_payload, verify_source, write_metadata, manifest_paths)


def build(target, destination, cache, source=None):
    spec = CONFIG['targets'][target]
    if (platform.system(), platform.machine()) != (spec['system'], spec['machine']):
        raise ValueError('Media build requires its native target: ' + target)
    env = os.environ.copy()
    cc = env.get('MEDIA_CC', spec['cc'])
    bash, make, strip = 'bash', 'make', 'strip'
    if target == 'windows-amd64':
        msys = Path(env.get('MEDIA_MSYS_ROOT', 'C:/msys64'))
        cc = (msys / 'ucrt64/bin/gcc.exe').as_posix()
        bash = str(msys / 'usr/bin/bash.exe')
        make = str(msys / 'usr/bin/make.exe')
        strip = str(msys / 'ucrt64/bin/strip.exe')
        env['PATH'] = str(msys / 'ucrt64/bin') + os.pathsep + str(msys / 'usr/bin') + os.pathsep + env.get('PATH', '')
        env['MSYSTEM'] = 'UCRT64'
    compiler = run([cc, '--version'], env=env).splitlines()[0]
    key = cache_key(target, compiler + '\n' + platform.platform())
    cache, destination = Path(cache), Path(destination)
    completed = cache / key / 'payload'
    if completed.exists():
        verify_payload(completed, target, key)
    else:
        source = Path(source) if source else acquire_source(cache / ('ffmpeg-' + CONFIG['ffmpeg_version'] + '.tar.xz'))
        verify_source(source)
        cache.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='compile-', dir=cache) as temp:
            work = Path(temp)
            tree = extract_source(source, work)
            payload = work / 'payload'
            (payload / 'libexec').mkdir(parents=True)
            args = [*CONFIG['configure_args'], '--cc=' + cc]
            if target.startswith('linux-'):
                args += ['--extra-cflags=-Os', '--extra-ldflags=-static', '--pkg-config=false']
            elif target == 'macos-arm64':
                env['MACOSX_DEPLOYMENT_TARGET'] = '15.0'
                args += ['--extra-cflags=-Os -mmacosx-version-min=15.0',
                         '--extra-ldflags=-mmacosx-version-min=15.0']
            else:
                args += ['--target-os=mingw32', '--arch=x86_64', '--disable-x86asm',
                         '--disable-pthreads', '--enable-w32threads',
                         '--extra-cflags=-Os', '--extra-ldflags=-static -static-libgcc']
            try:
                print(run([bash, './configure', *args], tree, env), flush=True)
                print(run([make, '-j' + str(min(os.cpu_count() or 2, 8)),
                           *[Path(p).name for p in manifest_paths(target)[2:]]], tree, env), flush=True)
            except subprocess.CalledProcessError as error:
                print(error.stdout, file=sys.stderr)
                raise
            suffix = '.exe' if target == 'windows-amd64' else ''
            for name in ['ffmpeg', 'ffprobe']:
                binary = payload / 'libexec' / (name + suffix)
                shutil.copy2(tree / (name + suffix), binary)
                run([strip, binary], env=env)
                binary.chmod(0o755)
                if target == 'macos-arm64':
                    run(['codesign', '--force', '--sign', '-', '--identifier', 'rarus-echo-' + name, binary])
                    run(['codesign', '--verify', '--strict', binary])
            licenses = payload / 'media-licenses'
            licenses.mkdir()
            shutil.copy2(tree / 'COPYING.LGPLv2.1', licenses / 'FFmpeg-LGPL-2.1.txt')
            shutil.copy2(tree / 'LICENSE.md', licenses / 'FFmpeg-LICENSE.md')
            if target.startswith('linux-'):
                for name in ['GCC-COPYING3.txt', 'GCC-COPYING.RUNTIME.txt']:
                    shutil.copy2(ROOT / 'build/licenses' / name, licenses / name)
                shutil.copy2(HERE / 'musl-COPYRIGHT.txt', licenses / 'musl-COPYRIGHT.txt')
            elif target == 'windows-amd64':
                for name in ['GCC-COPYING3.txt', 'GCC-COPYING.RUNTIME.txt']:
                    shutil.copy2(ROOT / 'build/licenses' / name, licenses / name)
                # MinGW-w64 imports and startup code have a separate permissive notice.
                shutil.copy2(HERE / 'mingw-w64-COPYING.txt', licenses / 'mingw-w64-COPYING.txt')
            source_package(source, payload, args, compiler, key)
            write_metadata(payload, target, args, compiler, key)
            run([sys.executable, HERE / 'accept.py', payload, '--target', target, '--linkage'])
            completed.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(payload), completed)
    if destination.exists():
        shutil.rmtree(destination)
    shutil.copytree(completed, destination)
    verify_payload(destination, target, key)
    print(run([sys.executable, HERE / 'accept.py', destination, '--target', target, '--linkage']))
    return json.loads((destination / 'media-build-info.json').read_text())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('target', choices=CONFIG['targets'])
    parser.add_argument('--destination', type=Path, required=True)
    parser.add_argument('--cache', type=Path, default=ROOT / 'runtime/media-cache')
    parser.add_argument('--source', type=Path)
    args = parser.parse_args()
    build(args.target, args.destination, args.cache, args.source)


if __name__ == '__main__':
    main()
