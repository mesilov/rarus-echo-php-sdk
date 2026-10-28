"""Contract tests for archives and source identity; no compiler or API access."""
import json
import hashlib
import os
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
from media import toolkit

SHA = "a" * 40
PACKAGING_LICENSES = ["BITRIX24-CLI-LICENSE.txt", "YIIPRESS-LICENSE.txt"]
RC2_SHA = "ba667107bb7611cbc0f3f47859cb63e970a635b9"


class ArchiveTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def platform(self, target, sha=SHA):
        root = self.root / target
        root.mkdir()
        binary = "rarus-echo.exe" if target.startswith("windows") else "rarus-echo"
        for name in [n for n in release_assets.REQUIRED if not n.startswith("media-") and n != "libexec"] + [binary]:
            path = root / name
            if name == "runtime-licenses":
                path.mkdir()
                (path / "PHP.txt").write_text("license", encoding="utf-8")
            elif name == "THIRD-PARTY-NOTICES.md":
                shutil.copy2(Path(__file__).resolve().parents[1] / name, path)
            elif name == "build-info.json":
                path.write_text(json.dumps({"version": "v0.5.0", "source_sha": sha, "target": target}), encoding="utf-8")
            else:
                path.write_text(name, encoding="utf-8")
        (root / "libexec").mkdir()
        suffix = ".exe" if target.startswith("windows") else ""
        for tool in ["ffmpeg", "ffprobe"]:
            path = root / "libexec" / (tool + suffix)
            path.write_bytes(tool.encode())
            path.chmod(0o755)
        (root / "media-source.tar.xz").write_bytes(b"compressed source fixture")
        (root / "media-licenses").mkdir()
        (root / "media-licenses/FFmpeg.txt").write_text("license")
        toolkit.write_metadata(root, target, toolkit.CONFIG["configure_args"], "fixture compiler", "fixture")
        info = json.loads((root / "build-info.json").read_text())
        info["media"] = toolkit.identity()
        (root / "build-info.json").write_text(json.dumps(info))
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
                    notices = archive.read("THIRD-PARTY-NOTICES.md").decode()
            else:
                with tarfile.open(path) as archive:
                    names = archive.getnames()
                    notices = archive.extractfile("THIRD-PARTY-NOTICES.md").read().decode()
                    self.assertEqual(0o755, archive.getmember("rarus-echo").mode)
            self.assertNotIn(".env", names)
            self.assertNotIn("runtime-probe", names)
            for name in PACKAGING_LICENSES:
                self.assertNotIn(name, names)
            self.assertIn("runtime-licenses/PHP.txt", names)
            self.assertIn("media-licenses/FFmpeg.txt", names)
            for name in PACKAGING_LICENSES:
                original = (Path(__file__).resolve().parents[1] / name).read_text()
                self.assertIn(original, notices)

    def test_historical_rc2_requires_original_licenses_and_exact_identity(self):
        for target in release_assets.TARGETS:
            with self.subTest(target=target):
                root = self.platform(target, sha=RC2_SHA)
                info = json.loads((root / "build-info.json").read_text())
                info["version"] = "0.5.0-rc-2"
                (root / "build-info.json").write_text(json.dumps(info))
                for name in PACKAGING_LICENSES:
                    (root / name).write_text("historical build-helper license")
                archive = self.root / release_assets.archive_name(target)
                files = sorted(p for p in root.rglob('*') if p.is_file() and p.name not in {'.env', 'runtime-probe'})
                release_assets._pack_files(files, root, archive, target)
                release_assets.inspect_archive(archive, target, "0.5.0-rc-2", RC2_SHA)
                # Both tag and source must match before accepting old files.
                for version, sha in [("0.5.0-rc-2", SHA), ("0.5.0-future", RC2_SHA)]:
                    info.update(version=version, source_sha=sha)
                    (root / "build-info.json").write_text(json.dumps(info))
                    release_assets._pack_files(files, root, archive, target)
                    with self.assertRaisesRegex(ValueError, "unexpected"):
                        release_assets.inspect_archive(archive, target, version, sha)
                info.update(version="0.5.0-rc-2", source_sha=RC2_SHA)
                (root / "build-info.json").write_text(json.dumps(info))
                # A missing old license must not silently choose the reduced shape.
                release_assets._pack_files([p for p in files if p.name != PACKAGING_LICENSES[0]], root, archive, target)
                with self.assertRaisesRegex(ValueError, "Incomplete"):
                    release_assets.inspect_archive(archive, target, "0.5.0-rc-2", RC2_SHA)

    def test_new_archive_rejects_each_historical_packaging_license(self):
        for target in release_assets.TARGETS:
            root = self.platform(target)
            # The producer must ignore stale files outside the distribution list.
            for name in PACKAGING_LICENSES:
                (root / name).write_text("build helper only")
            archive = release_assets.pack(root, target, self.root)
            for name in PACKAGING_LICENSES:
                with self.subTest(target=target, name=name):
                    if target.startswith('windows'):
                        with zipfile.ZipFile(archive, 'a') as output:
                            output.writestr(name, 'build helper only')
                    else:
                        bad = self.root / 'with-extra.tar.gz'
                        with tarfile.open(archive) as source, tarfile.open(bad, 'w:gz') as output:
                            for member in source:
                                output.addfile(member, source.extractfile(member))
                            output.add(root / name, arcname=name)
                        archive = bad
                    with self.assertRaisesRegex(ValueError, "unexpected"):
                        release_assets.inspect_archive(archive, target, "v0.5.0", SHA)
                    # Restore the valid archive before injecting the next file.
                    archive = release_assets.pack(root, target, self.root)

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

    def test_tampered_media_is_rejected_before_archiving(self):
        root = self.platform("linux-arm64")
        (root / "libexec/ffprobe").write_bytes(b"tampered")
        with self.assertRaisesRegex(ValueError, "hash|checksum"):
            release_assets.pack(root, "linux-arm64", self.root)

    def test_missing_tool_is_fatal(self):
        root = self.platform("macos-arm64")
        (root / "libexec/ffmpeg").unlink()
        with self.assertRaises((ValueError, FileNotFoundError)):
            release_assets.pack(root, "macos-arm64", self.root)

    def test_all_three_unix_executables_have_executable_mode(self):
        output = self.archives()
        with tarfile.open(output / release_assets.archive_name("linux-amd64")) as archive:
            for name in ["rarus-echo", "libexec/ffmpeg", "libexec/ffprobe"]:
                self.assertIn(name, archive.getnames())
                self.assertEqual(0o755, archive.getmember(name).mode)

    def test_archive_validator_rejects_case_collisions_and_bad_modes(self):
        root = self.platform("linux-amd64")
        output = release_assets.pack(root, "linux-amd64", self.root)
        for bad_name, mode in [("media-licenses/ffmpeg.txt", 0o644),
                               ("libexec/ffprobe", 0o644)]:
            bad = self.root / "bad.tar.gz"
            with tarfile.open(output) as source, tarfile.open(bad, "w:gz") as target:
                for member in source:
                    if member.name == bad_name:
                        member.mode = mode
                    target.addfile(member, source.extractfile(member))
                if bad_name.startswith("media-licenses"):
                    member = tarfile.TarInfo(bad_name)
                    member.mode = mode
                    member.size = 0
                    target.addfile(member)
            with self.assertRaises(ValueError):
                release_assets.inspect_archive(bad, "linux-amd64", "v0.5.0", SHA)

    def test_archive_validator_rejects_file_parent_and_component_case_collisions(self):
        root = self.platform("linux-amd64")
        output = release_assets.pack(root, "linux-amd64", self.root)
        for additions in [["runtime-licenses/collision", "runtime-licenses/collision/child"],
                          ["media-licenses/Foo/a", "media-licenses/foo/b"]]:
            bad = self.root / "collision.tar.gz"
            with tarfile.open(output) as source, tarfile.open(bad, "w:gz") as target:
                for member in source:
                    target.addfile(member, source.extractfile(member))
                for name in additions:
                    member = tarfile.TarInfo(name)
                    member.mode = 0o644
                    member.size = 0
                    target.addfile(member)
            with self.assertRaisesRegex(ValueError, "collision|case|parent"):
                release_assets.inspect_archive(bad, "linux-amd64", "v0.5.0", SHA)

    def test_missing_license_is_fatal(self):
        root = self.platform("linux-amd64")
        (root / "THIRD-PARTY-NOTICES.md").unlink()
        with self.assertRaises(FileNotFoundError):
            release_assets.pack(root, "linux-amd64", self.root)


class SourceTests(unittest.TestCase):
    def test_provenance_inputs_keep_canonical_bytes_in_autocrlf_checkout(self):
        project = Path(__file__).resolve().parents[2]
        config = json.loads((project / "build/config.json").read_text())
        inputs = [config["dependency_lock"], *config["toolchain_patches"],
                  "build/media/config.json", "tests/Fixtures/Media/manifest.json",
                  "tests/Fixtures/Media/corrupt.mp4"]
        with tempfile.TemporaryDirectory() as temp:
            fixture, checkout = Path(temp) / "fixture", Path(temp) / "checkout"
            fixture.mkdir()
            for name in [".gitattributes", *inputs]:
                destination = fixture / name
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(project / name, destination)
            def git(*args):
                return subprocess.check_output(["git", "-C", str(fixture), *args], text=True).strip()
            git("init", "-q")
            # Fixture repositories are deleted immediately; background maintenance can
            # recreate files while TemporaryDirectory is removing their .git folder.
            git("config", "maintenance.auto", "false")
            git("config", "gc.auto", "0")
            git("config", "core.autocrlf", "false")
            git("config", "user.name", "Packaging test")
            git("config", "user.email", "test@example.invalid")
            git("add", ".")
            git("commit", "-qm", "fixture")
            subprocess.run(["git", "-c", "maintenance.auto=false", "-c", "gc.auto=0",
                            "clone", "-q", "-c", "core.autocrlf=true",
                            str(fixture), str(checkout)], check=True)
            for name in inputs:
                self.assertEqual(hashlib.sha256((fixture / name).read_bytes()).hexdigest(),
                                 hashlib.sha256((checkout / name).read_bytes()).hexdigest(), name)

    def test_rejects_dirty_checkout_mismatch_and_ignored_application_files(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            def git(*args):
                return subprocess.check_output(["git", "-C", temp, *args], text=True).strip()
            git("init", "-q")
            git("config", "maintenance.auto", "false")
            git("config", "gc.auto", "0")
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
                                    cwd=root, capture_output=True, text=True,
                                    env=os.environ | {"ECHO_SOURCE_SHA": head})
            self.assertNotEqual(0, result.returncode)
            self.assertIn("clean checkout", result.stderr)
            (root / "untracked").unlink()
            (root / "src/private.secret").write_text("secret")
            with self.assertRaisesRegex(ValueError, "Ignored"):
                verify_checkout(root)


if __name__ == "__main__":
    unittest.main()
