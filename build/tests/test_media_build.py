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

    def test_portable_manifest_is_exact_four_record_complete_coverage(self):
        media = self.toolkit()
        self.assertEqual(['media-build-info.json', 'media-source.tar.xz',
                          'libexec/ffmpeg.exe', 'libexec/ffprobe.exe'],
                         media.manifest_paths('windows-amd64'))
        self.assertEqual(25 * 1024 * 1024, media.CONFIG['maximum_compressed_increment'])


if __name__ == '__main__':
    unittest.main()
