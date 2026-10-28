#!/usr/bin/env python3
"""Regression coverage for compressor failures, payload preservation and reports."""
import json
import importlib.util
import hashlib
import tarfile
import zipfile
from pathlib import Path
import subprocess
import struct
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import compress as compression


class CompressionChecks(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = Path(self.directory.name)

    def tearDown(self):
        self.directory.cleanup()

    def test_valid_cached_archive_is_reused_without_network(self):
        archive = self.root / "upx.xz"
        archive.write_bytes(b"verified tool archive")
        with patch("compress.urllib.request.urlretrieve", side_effect=AssertionError("network")):
            self.assertEqual(compression.checked_download("fixture", archive, compression.digest(archive)), archive)

    def test_wrong_cached_digest_is_rejected(self):
        archive = self.root / "upx.xz"
        archive.write_bytes(b"changed")
        with self.assertRaisesRegex(RuntimeError, "SHA256"):
            compression.checked_download("fixture", archive, "0" * 64)

    def test_wrong_download_digest_is_rejected_without_install(self):
        archive = self.root / "upx.xz"
        def download(url, dest):
            Path(dest).write_bytes(b"substituted tool")
        with patch("compress.urllib.request.urlretrieve", side_effect=download):
            with self.assertRaisesRegex(RuntimeError, "SHA256"):
                compression.checked_download("fixture", archive, "0" * 64)
        self.assertFalse(archive.exists())
        self.assertFalse(archive.with_suffix(".xz.download").exists())

    def test_unexpected_tool_version_is_rejected(self):
        with patch("compress.subprocess.check_output", return_value="upx 5.1.0\n"):
            with self.assertRaisesRegex(RuntimeError, "version"):
                compression.check_version("upx", "5.2.1")

    def test_expected_tool_version_is_accepted(self):
        for name, version in [("upx", "5.2.1"), ("wrappe", "1.0.4")]:
            with patch("compress.subprocess.check_output", return_value=name + " " + version + "\n"):
                compression.check_version(name, version)

    def test_no_output_empty_or_no_reduction_is_rejected(self):
        original = self.root / "original"
        final = self.root / "final"
        original.write_bytes(b"original executable")
        for content in [None, b"", original.read_bytes(), original.read_bytes() * 2]:
            if content is not None:
                final.write_bytes(content)
            with self.assertRaisesRegex(RuntimeError, "smaller"):
                compression.require_reduction(original, final)

    def test_upx_failure_preserves_original_runtime(self):
        original = self.root / "original"
        original.write_bytes(b"original runtime")
        (self.root / "packers").mkdir()
        with patch("compress.install_upx", return_value=self.root / "upx"), \
             patch("compress.subprocess.run", side_effect=subprocess.CalledProcessError(1, "upx")):
            with self.assertRaises(subprocess.CalledProcessError):
                compression.compress_micro("linux-amd64", self.root, original)
        self.assertEqual(original.read_bytes(), b"original runtime")

    def test_micro_is_packed_and_tested_on_a_copy(self):
        original = self.root / "original"
        original.write_bytes(b"runtime" * 100)
        (self.root / "packers").mkdir()
        commands = []
        def run(args, **kwargs):
            commands.append(args)
            if "--best" in args:
                Path(args[-1]).write_bytes(b"packed runtime")
        with patch("compress.install_upx", return_value=self.root / "upx"), \
             patch("compress.subprocess.run", side_effect=run):
            result = compression.compress_micro("windows-amd64", self.root, original)
        self.assertEqual(original.read_bytes(), b"runtime" * 100)
        self.assertEqual(result.read_bytes(), b"packed runtime")
        self.assertEqual([args[1] for args in commands], ["--best", "--test"])

    def test_linux_upx_trailer_is_removed_at_load_segment_end(self):
        binary = self.root / "micro.sfx"
        data = bytearray(440)
        data[:6] = b'\x7fELF\x02\x01'
        struct.pack_into('<Q', data, 32, 64)
        struct.pack_into('<HH', data, 54, 56, 2)
        struct.pack_into('<IIQQQQQQ', data, 64, 1, 6, 0, 0, 0, 0, 1024, 4096)
        struct.pack_into('<IIQQQQQQ', data, 120, 1, 5, 0, 0, 0, 400, 400, 4096)
        data[400:] = b'UPX trailer' * 3 + b'padding'
        binary.write_bytes(data)
        compression.finalize_upx_elf(binary)
        self.assertEqual(binary.read_bytes(), data[:400])

    def test_invalid_linux_upx_layout_is_rejected_without_modification(self):
        binary = self.root / "micro.sfx"
        binary.write_bytes(b'wrong ELF')
        before = binary.read_bytes()
        with self.assertRaisesRegex(ValueError, 'UPX ELF'):
            compression.finalize_upx_elf(binary)
        self.assertEqual(binary.read_bytes(), before)

    def test_changed_cached_wrappe_executable_is_rejected(self):
        root = self.root / 'packers'
        tool = root / 'bin/wrappe'
        tool.parent.mkdir(parents=True)
        tool.write_bytes(b'original cached tool')
        stamp = {'provenance': compression.provenance('macos-arm64'), 'tool_sha256': compression.digest(tool)}
        (root / 'wrappe-build-info.json').write_text(json.dumps(stamp))
        tool.write_bytes(b'changed cached tool')
        with patch('compress.checked_download', return_value=root / 'wrappe.crate'):
            with self.assertRaisesRegex(RuntimeError, 'Cached wrappe executable SHA256'):
                compression.install_wrappe(self.root, {})

    def test_verified_wrappe_source_patch_preserves_scoped_cargo_home(self):
        # Use the pinned package content shape, with a real tar archive.
        import io
        archive = self.root / 'wrappe.crate'
        content = b'if env.starts_with("CARGO") {\n    command.env_remove(&env);\n}\n    let active_targets = get_runner_targets();\n'
        with tarfile.open(archive, 'w') as crate:
            member = tarfile.TarInfo('wrappe-1.0.4/build.rs')
            member.size = len(content)
            crate.addfile(member, io.BytesIO(content))
        source = compression.prepare_wrappe_source(archive, self.root)
        self.assertIn('&& env != "CARGO_HOME"', (source / 'build.rs').read_text())

    def test_runner_notices_survive_cache_without_host_registry(self):
        packers = self.root / 'packers'
        host = self.root / 'host'
        for path in [packers / 'cargo-home/registry/src/index/packer-1/LICENSE',
                     packers / 'cargo-home/registry/src/index/runner-1/LICENSE']:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(path.parent.name + ' license')
        notices = packers / 'wrappe-notices'
        compression.collect_wrappe_notices(packers, notices)
        self.assertEqual((notices / 'runner-1/LICENSE').read_text(), 'runner-1 license')
        self.assertEqual((notices / 'packer-1/LICENSE').read_text(), 'packer-1 license')
        import shutil
        shutil.rmtree(packers / 'cargo-home')
        output = self.root / 'licenses'
        compression.export_notices('macos-arm64', self.root, output)
        self.assertTrue((output / 'wrappe-dependencies/runner-1/LICENSE').is_file())

    def fixture_report(self):
        (self.root / "uncompressed").mkdir()
        for name in ["rarus-echo", "runtime-probe"]:
            (self.root / "uncompressed" / name).write_bytes(b"original " * 100)
            (self.root / name).write_bytes(b"packed")
        info = {"executable_compression": compression.provenance("linux-amd64")}
        (self.root / "build-info.json").write_text(json.dumps(info))
        return compression.write_report(self.root, "linux-amd64")

    def test_exact_report_passes_and_changed_final_bytes_fail(self):
        self.fixture_report()
        compression.verify_report(self.root)
        (self.root / "rarus-echo").write_bytes(b"change")
        with self.assertRaisesRegex(RuntimeError, "SHA256"):
            compression.verify_report(self.root)

    def test_changed_original_bytes_fail(self):
        self.fixture_report()
        (self.root / "uncompressed/rarus-echo").write_bytes(b"changed " * 100)
        with self.assertRaisesRegex(RuntimeError, "SHA256"):
            compression.verify_report(self.root)

    def test_unpinned_compressor_report_is_rejected(self):
        report = self.fixture_report()
        report["compressor"]["version"] = "unverified"
        (self.root / "compression-info.json").write_text(json.dumps(report))
        with self.assertRaisesRegex(RuntimeError, "pinned"):
            compression.verify_report(self.root)

    def test_macos_packer_signs_outer_and_keeps_inner_bytes(self):
        binary = self.root / "rarus-echo"
        original = b"signed original" * 100
        binary.write_bytes(original)
        calls = []
        def run(args, **kwargs):
            calls.append(args)
            if "--compression" in args:
                self.assertEqual((self.root / "wrappe-input-rarus-echo/rarus-echo").read_bytes(), original)
                Path(args[-1]).write_bytes(b"compressed signed launcher")
        with patch("compress.install_wrappe", return_value=self.root / "wrappe"), \
             patch("compress.subprocess.run", side_effect=run), \
             patch("macos.finalize_overlay") as finalize:
            compression.pack_macos(self.root, binary, {})
        self.assertEqual(binary.read_bytes(), b"compressed signed launcher")
        self.assertEqual(calls[0][calls[0].index("--show-information") + 1], "none")
        finalize.assert_called_once_with(binary.with_name("rarus-echo.packed"))
        self.assertFalse(any("--cleanup" in args for args in calls))

    def test_macos_packer_failure_does_not_replace_original(self):
        binary = self.root / "rarus-echo"
        binary.write_bytes(b"signed original")
        with patch("compress.install_wrappe", return_value=self.root / "wrappe"), \
             patch("compress.subprocess.run", side_effect=subprocess.CalledProcessError(1, "wrappe")):
            with self.assertRaises(subprocess.CalledProcessError):
                compression.pack_macos(self.root, binary, {})
        self.assertEqual(binary.read_bytes(), b"signed original")


if __name__ == "__main__":
    unittest.main()
