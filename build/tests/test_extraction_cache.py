"""The documented launcher cache must not hide unrelated temporary-file leaks."""
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('installed_media', Path(__file__).with_name('installed-media-check.py'))
installed = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installed)


class CacheCleanupTests(unittest.TestCase):
    def test_only_declared_macos_cache_survives_and_media_leaks_still_fail(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            executable = root / 'rarus-echo-cache/version/rarus-echo'
            executable.parent.mkdir(parents=True)
            executable.write_bytes(b'extracted CLI')
            metadata = {'target': 'macos-arm64', 'executable_compression': {'tool': 'wrappe', 'cache_directory': 'rarus-echo-cache'}}
            installed.assert_temporary_cleanup(root, metadata)
            with self.assertRaisesRegex(AssertionError, 'temporary files'):
                installed.assert_temporary_cleanup(root, {'target': 'linux-amd64'})
            (root / 'rarus-echo-media-leak').mkdir()
            with self.assertRaisesRegex(AssertionError, 'temporary files'):
                installed.assert_temporary_cleanup(root, metadata)

    def test_empty_or_different_cache_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'rarus-echo-cache').mkdir()
            metadata = {'target': 'macos-arm64', 'executable_compression': {'tool': 'wrappe', 'cache_directory': 'rarus-echo-cache'}}
            with self.assertRaisesRegex(AssertionError, 'extraction cache'):
                installed.assert_temporary_cleanup(root, metadata)


if __name__ == '__main__':
    unittest.main()
