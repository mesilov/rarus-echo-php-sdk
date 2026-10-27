#!/usr/bin/env python3
"""Report/gate complete compressed media bytes against the same CLI without media."""
import argparse
import json
from pathlib import Path
import sys
import tarfile
import tempfile
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tests'))
from release_assets import _pack_files, archive_name
from smoke import extract_archive
from media.toolkit import CONFIG


def report(path):
    path = Path(path)
    if path.suffix == '.zip':
        with zipfile.ZipFile(path) as archive:
            info = json.loads(archive.read('build-info.json'))
    else:
        with tarfile.open(path) as archive:
            info = json.load(archive.extractfile('build-info.json'))
    with tempfile.TemporaryDirectory(prefix='echo-media-size-') as temp:
        root = Path(temp) / 'payload'
        root.mkdir()
        extract_archive(path, root, info['version'], info['source_sha'])
        files = sorted(p for p in root.rglob('*') if p.is_file())
        baseline_files = [p for p in files if not p.relative_to(root).as_posix().startswith(('media-', 'libexec/'))]
        baseline = Path(temp) / archive_name(info['target'])
        _pack_files(baseline_files, root, baseline, info['target'])
        delta = path.stat().st_size - baseline.stat().st_size
        value = {'baseline': '7f41160 archive layout, same CLI/runtime with media payload omitted',
                 'target': info['target'], 'baseline_compressed_bytes': baseline.stat().st_size,
                 'complete_compressed_bytes': path.stat().st_size,
                 'compressed_media_increment_bytes': delta,
                 'media_uncompressed_bytes': sum(p.stat().st_size for p in files if p not in baseline_files),
                 'maximum_compressed_media_increment_bytes': CONFIG['maximum_compressed_increment']}
        if delta > CONFIG['maximum_compressed_increment']:
            raise ValueError('Complete compressed media increment exceeds 25 MiB')
        return value


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    args = parser.parse_args()
    print(json.dumps(report(args.archive), indent=2))
