"""Offline media source, provenance, cache and package contracts."""
import copy
import hashlib
import io
import json
from pathlib import Path
import sys
import tarfile
import tempfile
import subprocess
import os
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))


class MediaBuildTests(unittest.TestCase):
    def toolkit(self):
        self.assertTrue((Path(__file__).resolve().parents[1] / 'media/toolkit.py').is_file(),
                        'the verified media builder is required')
        from media import toolkit
        return toolkit

    def test_size_report_entrypoint_runs_from_an_unrelated_directory(self):
        script = Path(__file__).resolve().parents[1] / 'media/archive_size.py'
        with tempfile.TemporaryDirectory() as temp:
            result = subprocess.run([sys.executable, str(script), '--help'], cwd=temp,
                                    capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stderr)

    def test_capability_gate_rejects_extra_audio_and_any_video_codec(self):
        media = self.toolkit()
        self.assertTrue(hasattr(media, 'validate_codecs'), 'capability allowlist gate is required')
        encoders = ' A....D flac description\n A....D pcm_s16le description\n A....D pcm_s24le description\n'
        media.validate_codecs(encoders, 'encoder')
        for extra in [' A....D aac unexpected\n', ' V....D h264 unexpected\n']:
            with self.assertRaisesRegex(ValueError, 'codec'):
                media.validate_codecs(encoders + extra, 'encoder')

    def test_source_checksum_mismatch_is_rejected_before_extraction(self):
        media = self.toolkit()
        with tempfile.TemporaryDirectory() as temp:
            source = Path(temp) / 'source.tar.xz'
            source.write_bytes(b'not the upstream archive')
            with self.assertRaisesRegex(ValueError, 'checksum'):
                media.verify_source(source)

    def test_extraction_rejects_links_traversal_duplicates_and_expansion(self):
        media = self.toolkit()
        for names, kind in [(['ffmpeg-9.0.2/../../escape'], 'file'),
                            (['ffmpeg-9.0.2/link'], 'link'),
                            (['ffmpeg-9.0.2/a', 'ffmpeg-9.0.2/a'], 'file'),
                            (['other-root/a'], 'file'),
                            (['ffmpeg-9.0.2/C:/escape'], 'file'),
                            (['ffmpeg-9.0.2/control\nname'], 'file')]:
            with self.subTest(names=names, kind=kind), tempfile.TemporaryDirectory() as temp:
                source = Path(temp) / 'source.tar'
                with tarfile.open(source, 'w') as archive:
                    for name in names:
                        member = tarfile.TarInfo(name)
                        member.size = 1
                        if kind == 'link':
                            member.type = tarfile.SYMTYPE
                            member.linkname = '/outside'
                        archive.addfile(member, io.BytesIO(b'a'))
                with self.assertRaises(ValueError):
                    media.extract_source(source, Path(temp) / 'out')

    def test_cache_key_changes_for_target_compiler_profile_or_recipe(self):
        media = self.toolkit()
        key = media.cache_key('macos-arm64', 'clang 1')
        self.assertNotEqual(key, media.cache_key('linux-arm64', 'clang 1'))
        self.assertNotEqual(key, media.cache_key('macos-arm64', 'clang 2'))
        profile = copy.deepcopy(media.CONFIG)
        profile['configure_args'].append('--disable-asm')
        self.assertNotEqual(key, media.cache_key('macos-arm64', 'clang 1', config=profile))
        self.assertNotEqual(key, media.cache_key('macos-arm64', 'clang 1', recipe_sha='b' * 64))

    def test_payload_rejects_wrong_target_stale_profile_or_tampered_binary(self):
        media = self.toolkit()
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / 'libexec').mkdir()
            for name in ['ffmpeg', 'ffprobe']:
                path = root / 'libexec' / name
                path.write_bytes(name.encode())
                path.chmod(0o755)
            (root / 'media-source.tar.xz').write_bytes(b'source fixture')
            (root / 'media-licenses').mkdir()
            (root / 'media-licenses/LGPL-2.1.txt').write_text('license')
            media.write_metadata(root, 'macos-arm64', media.CONFIG['configure_args'], 'clang', 'fixture')
            media.verify_payload(root, 'macos-arm64')
            outside = root / 'other-tools'
            (root / 'libexec').rename(outside)
            try:
                (root / 'libexec').symlink_to(outside, target_is_directory=True)
            except OSError:
                outside.rename(root / 'libexec')
            else:
                with self.assertRaisesRegex(ValueError, 'regular|symlink|safe'):
                    media.verify_payload(root, 'macos-arm64')
                (root / 'libexec').unlink()
                outside.rename(root / 'libexec')
            with self.assertRaisesRegex(ValueError, 'target'):
                media.verify_payload(root, 'linux-arm64')
            metadata = root / 'media-build-info.json'
            original = metadata.read_bytes()
            info = json.loads(original)
            info['profile_sha256'] = 'a' * 64
            metadata.write_text(json.dumps(info))
            with self.assertRaises(ValueError):
                media.verify_payload(root, 'macos-arm64')
            metadata.write_bytes(original)
            (root / 'libexec/ffprobe').write_bytes(b'tampered')
            with self.assertRaisesRegex(ValueError, 'hash|checksum'):
                media.verify_payload(root, 'macos-arm64')

    def media_files(self, root, media, target='windows-amd64'):
        (root / 'libexec').mkdir()
        for name in media.manifest_paths(target)[2:]:
            (root / name).write_bytes(name.encode())
        (root / 'media-source.tar.xz').write_bytes(b'fixture source')
        (root / 'media-licenses').mkdir()
        (root / 'media-licenses/LICENSE.txt').write_bytes(b'fixture license')

    def test_metadata_manifest_and_source_recipe_use_lf_on_windows_writers(self):
        media = self.toolkit()
        original = Path.write_text
        def windows_write(path, text, *args, **kwargs):
            # Emulate TextIOWrapper's Windows newline=None translation on every host.
            if kwargs.get('newline') is None:
                kwargs['newline'] = '\r\n'
            return original(path, text, *args, **kwargs)
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            self.media_files(root, media)
            source = root / 'upstream.tar.xz'
            source.write_bytes(b'fixture upstream archive')
            with patch.object(Path, 'write_text', windows_write):
                media.source_package(source, root, media.CONFIG['configure_args'], 'fixture compiler', 'fixture')
                media.write_metadata(root, 'windows-amd64', media.CONFIG['configure_args'], 'fixture compiler', 'fixture')
            paths = media.manifest_paths('windows-amd64')
            expected = ''.join(media.digest(root / name) + '  ' + name + '\n' for name in paths).encode('ascii')
            self.assertEqual(expected, (root / 'media-SHA256SUMS').read_bytes())
            self.assertNotIn(b'\r', (root / 'media-build-info.json').read_bytes())
            with tarfile.open(root / 'media-source.tar.xz') as archive:
                self.assertNotIn(b'\r', archive.extractfile('effective-build.json').read())
            media.verify_payload(root, 'windows-amd64')

    def test_payload_rejects_crlf_and_reordered_manifests_before_packaging(self):
        media = self.toolkit()
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            self.media_files(root, media)
            media.write_metadata(root, 'windows-amd64', media.CONFIG['configure_args'], 'fixture compiler', 'fixture')
            manifest = root / 'media-SHA256SUMS'
            canonical = manifest.read_bytes().replace(b'\r\n', b'\n')
            reordered = b'\n'.join(reversed(canonical.rstrip(b'\n').split(b'\n'))) + b'\n'
            for invalid in [canonical.replace(b'\n', b'\r\n'), reordered, canonical.rstrip(b'\n')]:
                manifest.write_bytes(invalid)
                with self.assertRaisesRegex(ValueError, 'canonical|manifest'):
                    media.verify_payload(root, 'windows-amd64')

    def test_corresponding_source_requires_the_console_helper_dependency(self):
        media = self.toolkit()
        source = b'fixture upstream source'
        source_hash = hashlib.sha256(source).hexdigest()
        config = dict(media.CONFIG, source_sha256=source_hash)
        names = ['recipe/build/media/toolkit.py', 'recipe/build/media/build.py',
                 'recipe/build/media/accept.py', 'recipe/build/media/README.md',
                 'recipe/build/media/upstream-source.tar.xz.asc', 'recipe/build/media/upstream-signing-key.asc',
                 'recipe/build/tests/linkage-check.py', 'recipe/tests/Fixtures/Media/manifest.json',
                 'effective-build.json']
        files = {name: b'fixture recipe' for name in names}
        files['ffmpeg-' + media.CONFIG['ffmpeg_version'] + '.tar.xz'] = source
        files['recipe/build/media/config.json'] = json.dumps(config).encode()
        with tempfile.TemporaryDirectory() as temp, patch.dict(media.CONFIG, {'source_sha256': source_hash}):
            root = Path(temp)
            def package():
                with tarfile.open(root / 'media-source.tar.xz', 'w:xz') as archive:
                    for name, data in files.items():
                        member = tarfile.TarInfo(name)
                        member.size = len(data)
                        archive.addfile(member, io.BytesIO(data))
            package()
            with self.assertRaisesRegex(ValueError, 'materials missing'):
                media.verify_source_package(root)
            files['recipe/build/media/console.py'] = b'# required UTF-8 build console helper\n'
            package()
            media.verify_source_package(root)

    def test_portable_manifest_is_exact_four_record_complete_coverage(self):
        media = self.toolkit()
        self.assertEqual(['media-build-info.json', 'media-source.tar.xz',
                          'libexec/ffmpeg.exe', 'libexec/ffprobe.exe'],
                         media.manifest_paths('windows-amd64'))
        self.assertEqual(25 * 1024 * 1024, media.CONFIG['maximum_compressed_increment'])


if __name__ == '__main__':
    unittest.main()
