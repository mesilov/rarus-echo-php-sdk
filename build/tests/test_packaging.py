"""Contract tests for archives and source identity; no compiler or API access."""
import json
from pathlib import Path
import subprocess
import shutil
import sys
import tarfile
import tempfile
import unittest
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import release_assets
from smoke import extract_archive
from source import verify_checkout

SHA = "a" * 40


class ArchiveTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def platform(self, target, sha=SHA):
        root = self.root / target
        root.mkdir()
        binary = "rarus-echo.exe" if target.startswith("windows") else "rarus-echo"
        for name in release_assets.REQUIRED + [binary]:
            path = root / name
            if name == "runtime-licenses":
                path.mkdir()
                (path / "PHP.txt").write_text("license", encoding="utf-8")
            elif name == "build-info.json":
                path.write_text(json.dumps({"version": "v0.5.0", "source_sha": sha, "target": target}), encoding="utf-8")
            else:
                path.write_text(name, encoding="utf-8")
        (root / ".env").write_text("must not be shipped")
        (root / "runtime-probe").write_text("must not be shipped")
        return root

    def archives(self):
        output = self.root / "archives"
        output.mkdir()
        for target in release_assets.TARGETS:
            release_assets.pack(self.platform(target), target, output)
        return output

    def test_archives_contain_only_distributable_files_and_unix_execute_bit(self):
        output = self.archives()
        for target in release_assets.TARGETS:
            path = output / release_assets.archive_name(target)
            if target.startswith("windows"):
                with zipfile.ZipFile(path) as archive:
                    names = archive.namelist()
            else:
                with tarfile.open(path) as archive:
                    names = archive.getnames()
                    self.assertEqual(0o755, archive.getmember("rarus-echo").mode)
            self.assertNotIn(".env", names)
            self.assertNotIn("runtime-probe", names)
            self.assertIn("runtime-licenses/PHP.txt", names)

    def test_extracts_tar_and_zip_without_tarfile_filter_support(self):
        output = self.archives()
        for target in release_assets.TARGETS:
            extracted = self.root / ("extracted-" + target)
            extracted.mkdir()
            extract_archive(output / release_assets.archive_name(target), extracted, "v0.5.0", SHA)
            self.assertTrue((extracted / release_assets.executable(target)).is_file())
            self.assertEqual("license", (extracted / "runtime-licenses/PHP.txt").read_text())

    def test_manifest_covers_four_archives_and_rejects_tampering(self):
        output = self.archives()
        release_assets.write_checksums(output, "v0.5.0", SHA)
        release_assets.verify_assets(output, "v0.5.0", SHA)
        self.assertEqual(4, len((output / "SHA256SUMS").read_text().splitlines()))
        archive = output / release_assets.archive_name("linux-arm64")
        archive.write_bytes(archive.read_bytes() + b"tamper")
        with self.assertRaisesRegex(ValueError, "checksum"):
            release_assets.verify_assets(output, "v0.5.0", SHA)

    def test_refuses_missing_platform_and_mixed_source(self):
        output = self.archives()
        (output / release_assets.archive_name("linux-arm64")).unlink()
        with self.assertRaises(ValueError):
            release_assets.write_checksums(output, "v0.5.0", SHA)
        release_assets.pack(self.root / "linux-arm64", "linux-arm64", output)
        with self.assertRaisesRegex(ValueError, "identity"):
            release_assets.write_checksums(output, "v0.5.0", "b" * 40)

    def test_missing_license_is_fatal(self):
        root = self.platform("linux-amd64")
        (root / "THIRD-PARTY-NOTICES.md").unlink()
        with self.assertRaises(FileNotFoundError):
            release_assets.pack(root, "linux-amd64", self.root)


class SourceTests(unittest.TestCase):
    def test_rejects_dirty_checkout_mismatch_and_ignored_application_files(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            def git(*args):
                return subprocess.check_output(["git", "-C", temp, *args], text=True).strip()
            git("init", "-q")
            git("config", "user.name", "Packaging test")
            git("config", "user.email", "test@example.invalid")
            (root / "build").mkdir()
            (root / "src").mkdir()
            (root / "src/main.php").write_text("<?php")
            (root / ".gitignore").write_text("*.secret\n")
            (root / "lock.json").write_text("{}")
            (root / "build/config.json").write_text(json.dumps({
                "application_paths": ["src"], "dependency_lock": "lock.json"}))
            for name in ["source.py", "package-linux.sh"]:
                shutil.copy2(Path(__file__).resolve().parents[1] / name, root / "build" / name)
            git("add", ".")
            git("commit", "-qm", "fixture")
            head = git("rev-parse", "HEAD")
            self.assertEqual(head, verify_checkout(root, head))
            with self.assertRaisesRegex(ValueError, "differs"):
                verify_checkout(root, "b" * 40)
            (root / "untracked").write_text("change")
            with self.assertRaisesRegex(ValueError, "clean"):
                verify_checkout(root)
            result = subprocess.run(["bash", str(root / "build/package-linux.sh")],
                                    cwd=root, capture_output=True, text=True)
            self.assertNotEqual(0, result.returncode)
            self.assertIn("clean checkout", result.stderr)
            (root / "untracked").unlink()
            (root / "src/private.secret").write_text("secret")
            with self.assertRaisesRegex(ValueError, "Ignored"):
                verify_checkout(root)


if __name__ == "__main__":
    unittest.main()
