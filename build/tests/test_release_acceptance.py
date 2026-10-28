"""Offline failure boundaries for public, native release acceptance."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
import zipfile
import stat
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import release_acceptance
import release_assets
from media import toolkit

VERSION = "0.5.0-rc-1"
SHA = "a" * 40
LEGACY_SHA = release_assets.LEGACY_MEDIALESS_RELEASES[VERSION]


class AcceptanceTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="release acceptance-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)

    def package(self, target="linux-amd64", version=VERSION, sha=SHA, media=True):
        package = self.root / ("verified package" if media else "legacy package")
        package.mkdir()
        for name in (release_assets.REQUIRED if media else release_assets.LEGACY_REQUIRED) + [release_assets.executable(target)]:
            path = package / name
            if name in {"runtime-licenses", "media-licenses", "libexec"}:
                path.mkdir()
                if name != "libexec":
                    (path / "LICENSE.txt").write_bytes(b"license\n")
            elif name == "build-info.json":
                path.write_text(json.dumps({"version": version, "source_sha": sha, "target": target}),
                                encoding="utf-8")
            else:
                path.write_bytes(name.encode("utf-8"))
        if media:
            for name in toolkit.manifest_paths(target)[2:]:
                (package / name).write_bytes(name.encode())
            toolkit.write_metadata(package, target, toolkit.CONFIG['configure_args'], 'fixture compiler', 'fixture')
            info = json.loads((package / 'build-info.json').read_text())
            info['media'] = toolkit.identity()
            (package / 'build-info.json').write_text(json.dumps(info))
        return package

    def archive(self, target="linux-amd64", version=VERSION, sha=SHA, media=True):
        package = self.package(target, version, sha, media)
        if media:
            path = release_assets.pack(package, target, self.root / "assets")
        else:
            path = self.root / release_assets.archive_name(target)
            release_assets._pack_files(sorted(p for p in package.rglob('*') if p.is_file()), package, path, target)
        digest = hashlib.sha256(path.read_bytes()).hexdigest()
        manifest = self.root / "SHA256SUMS"
        manifest.write_text("".join(
            (digest if candidate == target else "0" * 64) + "  "
            + release_assets.archive_name(candidate) + "\n" for candidate in release_assets.TARGETS),
            encoding="utf-8")
        return path, manifest

    def test_rejects_unsafe_tags_and_inexact_source_ids(self):
        release_acceptance.validate_identity(VERSION, SHA)
        for version in ["", "../main", "--help", "0.5.0/rc", "a..b", "a\nmain", "a" * 129]:
            with self.subTest(version=version), self.assertRaisesRegex(ValueError, "tag"):
                release_acceptance.validate_identity(version, SHA)
        for sha in ["a" * 39, "a" * 41, "A" * 40, "HEAD", "a" * 39 + ";"]:
            with self.subTest(sha=sha), self.assertRaisesRegex(ValueError, "SHA"):
                release_acceptance.validate_identity(VERSION, sha)

    def test_selects_only_supported_native_targets(self):
        for system, machine, target in [
                ("Linux", "x86_64", "linux-amd64"), ("Linux", "aarch64", "linux-arm64"),
                ("Darwin", "arm64", "macos-arm64"), ("Windows", "AMD64", "windows-amd64")]:
            with self.subTest(system=system, machine=machine):
                self.assertEqual(target, release_acceptance.native_target(system, machine))
        for system, machine in [("Darwin", "x86_64"), ("Windows", "ARM64"), ("Linux", "i686")]:
            with self.subTest(system=system, machine=machine), self.assertRaisesRegex(ValueError, "native"):
                release_acceptance.native_target(system, machine)

    def test_accepts_native_archive_with_exact_checksum_and_identity(self):
        archive, manifest = self.archive()
        release_acceptance.verify_archive(archive, manifest, "linux-amd64", VERSION, SHA)

    def test_legacy_archive_requires_explicit_tag_source_contract(self):
        archive, manifest = self.archive(media=False, sha=LEGACY_SHA)
        with self.assertRaisesRegex(ValueError, 'Incomplete|unexpected'):
            release_acceptance.verify_archive(archive, manifest, 'linux-amd64', VERSION, LEGACY_SHA)
        release_acceptance.verify_archive(archive, manifest, 'linux-amd64', VERSION, LEGACY_SHA, require_media=False)
        destination = self.root / 'extracted historical'
        destination.mkdir()
        release_acceptance.extract_archive(archive, destination, VERSION, LEGACY_SHA, require_media=False)
        self.assertTrue((destination / 'rarus-echo').is_file())
        self.assertFalse((destination / 'media-build-info.json').exists())

    def test_legacy_schema_cannot_accept_a_new_media_archive(self):
        archive, manifest = self.archive(sha=LEGACY_SHA)
        with self.assertRaisesRegex(ValueError, 'unexpected|Unexpected'):
            release_acceptance.verify_archive(archive, manifest, 'linux-amd64', VERSION, LEGACY_SHA, require_media=False)

    def test_unknown_version_or_source_cannot_select_legacy_shape(self):
        archive, manifest = self.archive(media=False, sha=LEGACY_SHA)
        for version, sha in [('0.5.0-future', LEGACY_SHA), (VERSION, 'b' * 40)]:
            with self.subTest(version=version, sha=sha), self.assertRaisesRegex(ValueError, 'approved published legacy'):
                release_acceptance.verify_archive(archive, manifest, 'linux-amd64', version, sha, require_media=False)

    def test_frozen_windows_zip_accepts_its_actual_ntfs_producer_modes_only(self):
        archive, manifest = self.archive(target='windows-amd64', media=False, sha=LEGACY_SHA)
        native = self.root / 'native.zip'
        with zipfile.ZipFile(archive) as source, zipfile.ZipFile(native, 'w') as target:
            for member in source.infolist():
                member.create_system = 0
                permission = 0o777 if member.filename == 'rarus-echo.exe' else 0o666
                member.external_attr = (stat.S_IFREG | permission) << 16
                target.writestr(member, source.read(member.filename))
        native.replace(archive)
        records = manifest.read_text().splitlines()
        manifest.write_text('\n'.join(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name
                                     if line.endswith('  ' + archive.name) else line for line in records) + '\n')
        release_acceptance.verify_archive(archive, manifest, 'windows-amd64', VERSION, LEGACY_SHA, require_media=False)
        with self.assertRaisesRegex(ValueError, 'permission|unexpected'):
            release_acceptance.verify_archive(archive, manifest, 'windows-amd64', VERSION, LEGACY_SHA)

    def test_rejects_missing_duplicate_and_malformed_checksum_records(self):
        archive, manifest = self.archive()
        valid = manifest.read_text(encoding="utf-8")
        records = valid.splitlines(keepends=True)
        for invalid in ["".join(records[1:]), valid + records[0],
                        valid.replace("0" * 64, "not-a-sha256", 1),
                        valid.replace("rarus-echo-linux-arm64.tar.gz", "unexpected.tar.gz")]:
            manifest.write_text(invalid, encoding="utf-8")
            with self.subTest(manifest=invalid), self.assertRaisesRegex(ValueError, "checksum manifest"):
                release_acceptance.verify_archive(archive, manifest, "linux-amd64", VERSION, SHA)

    def test_rejects_tampering_before_archive_inspection(self):
        archive, manifest = self.archive()
        archive.write_bytes(archive.read_bytes() + b"altered after publication")
        with self.assertRaisesRegex(ValueError, "checksum differs"):
            release_acceptance.verify_archive(archive, manifest, "linux-amd64", VERSION, SHA)

    def test_rejects_embedded_version_source_or_target_mismatch(self):
        archive, manifest = self.archive()
        for target, version, sha in [("linux-amd64", "0.5.0", SHA),
                                     ("linux-amd64", VERSION, "b" * 40),
                                     ("linux-arm64", VERSION, SHA)]:
            with self.subTest(target=target, version=version, sha=sha), self.assertRaisesRegex(ValueError, "identity"):
                release_acceptance.verify_archive(archive, manifest, target, version, sha)

    def git(self, *args):
        result = subprocess.run(["git", "-c", "user.name=Fixture", "-c", "user.email=fixture@example.test", *args],
                                cwd=self.root, check=True, capture_output=True, text=True)
        return result.stdout.strip()

    def test_requires_a_tag_in_main_history_and_expected_source_commit(self):
        self.git("init", "-b", "main")
        (self.root / "source").write_text("main\n", encoding="utf-8")
        self.git("add", "source")
        self.git("commit", "-m", "main source")
        main_sha = self.git("rev-parse", "HEAD")
        self.git("update-ref", "refs/remotes/origin/main", main_sha)
        self.git("tag", "-a", VERSION, "-m", VERSION)
        self.assertEqual(main_sha, release_acceptance.resolve_main_tag(self.root, VERSION))
        self.assertEqual(main_sha, release_acceptance.resolve_main_tag(self.root, VERSION, main_sha))
        with self.assertRaisesRegex(ValueError, "expected source SHA"):
            release_acceptance.resolve_main_tag(self.root, VERSION, "b" * 40)
        self.git("checkout", "-b", "feature")
        (self.root / "source").write_text("feature\n", encoding="utf-8")
        self.git("commit", "-am", "unmerged feature")
        self.git("tag", "0.5.0-feature")
        with self.assertRaisesRegex(ValueError, "main history"):
            release_acceptance.resolve_main_tag(self.root, "0.5.0-feature")
        with self.assertRaisesRegex(ValueError, "tag"):
            release_acceptance.resolve_main_tag(self.root, "missing")

    def test_public_installer_must_match_checked_out_bytes(self):
        public, source = self.root / "public.ps1", self.root / "source.ps1"
        public.write_bytes(b"Write-Host 'installer'\n")
        source.write_bytes(public.read_bytes())
        release_acceptance.verify_installer(public, source)
        public.write_bytes(b"Write-Host 'installer'\r\n")
        with self.assertRaisesRegex(ValueError, "installer differs"):
            release_acceptance.verify_installer(public, source)

    def test_smoke_commands_follow_tagged_source_instead_of_current_checkout(self):
        self.git('init', '-b', 'main')
        (self.root / 'source').write_text('before doctor\n', encoding='utf-8')
        self.git('add', 'source')
        self.git('commit', '-m', 'historical CLI')
        historical = self.git('rev-parse', 'HEAD')
        doctor = self.root / 'src/Infrastructure/Console/Command/DoctorCommand.php'
        doctor.parent.mkdir(parents=True)
        doctor.write_text('<?php // doctor introduced\n', encoding='utf-8')
        self.git('add', 'src')
        self.git('commit', '-m', 'current CLI with doctor')
        current = self.git('rev-parse', 'HEAD')
        with patch.dict(release_assets.LEGACY_MEDIALESS_RELEASES, {VERSION: historical}, clear=True):
            self.assertEqual(['--without-doctor', '--without-media'],
                             release_acceptance.release_smoke_options(self.root, historical, VERSION))
        with self.assertRaisesRegex(ValueError, 'approved published legacy'):
            release_acceptance.release_smoke_options(self.root, current, VERSION)
        media = self.root / 'build/media'
        media.mkdir(parents=True)
        (media / 'config.json').write_text('{}')
        self.git('add', 'build')
        self.git('commit', '-m', 'incomplete media source must fail closed')
        with self.assertRaisesRegex(ValueError, 'partial|incomplete'):
            release_acceptance.release_smoke_options(self.root, self.git('rev-parse', 'HEAD'), VERSION)
        for name in ['toolkit.py', 'build.py']:
            (media / name).write_text('# complete media contract marker\n')
        self.git('add', 'build')
        self.git('commit', '-m', 'current complete bundled media source')
        self.assertEqual([], release_acceptance.release_smoke_options(self.root, self.git('rev-parse', 'HEAD'), VERSION))
        with self.assertRaises(subprocess.CalledProcessError):
            release_acceptance.release_smoke_options(self.root, 'f' * 40, VERSION)

    def test_isolates_user_directories_and_removes_ambient_api_credentials(self):
        env = release_acceptance.isolated_environment(self.root, {
            "PATH": os.defpath, "HOME": "/real/home", "APPDATA": "/real/config",
            "RARUS_ECHO_API_KEY": "real-secret", "rarus_echo_user_id": "other-secret"})
        self.assertEqual(os.defpath, env["PATH"])
        self.assertFalse(any(key.upper().startswith("RARUS_ECHO_") for key in env))
        for key in ["HOME", "USERPROFILE", "APPDATA", "LOCALAPPDATA", "XDG_CONFIG_HOME", "TMP", "TEMP", "TMPDIR"]:
            with self.subTest(key=key):
                self.assertTrue(Path(env[key]).is_dir())
                self.assertTrue(Path(env[key]).is_relative_to(self.root))

    def test_installed_binary_and_notices_must_match_verified_archive(self):
        package = self.package()
        install = self.root / "installed CLI"
        install.mkdir()
        notices = self.root / "installed notices"
        shutil.copytree(package, notices)
        shutil.move(str(notices / "rarus-echo"), install / "rarus-echo")
        release_acceptance.verify_installation(install, notices, package, "linux-amd64")
        (notices / "runtime-licenses/LICENSE.txt").write_bytes(b"changed license")
        with self.assertRaisesRegex(ValueError, "Installed file differs"):
            release_acceptance.verify_installation(install, notices, package, "linux-amd64")
        shutil.copy2(package / "runtime-licenses/LICENSE.txt", notices / "runtime-licenses/LICENSE.txt")
        (install / "rarus-echo").write_bytes(b"different executable")
        with self.assertRaisesRegex(ValueError, "Installed file differs"):
            release_acceptance.verify_installation(install, notices, package, "linux-amd64")

    def test_versioned_package_requires_matching_cli_as_well_as_entrypoint(self):
        package = self.package()
        install, versioned = self.root / 'installed', self.root / 'versioned payload'
        install.mkdir()
        shutil.copytree(package, versioned)
        shutil.copy2(package / 'rarus-echo', install / 'rarus-echo')
        release_acceptance.verify_installation(install, versioned, package, 'linux-amd64', complete_package=True)
        (versioned / 'rarus-echo').write_bytes(b'wrong immutable package executable')
        with self.assertRaisesRegex(ValueError, 'Versioned package executable'):
            release_acceptance.verify_installation(install, versioned, package, 'linux-amd64', complete_package=True)

    def test_only_frozen_legacy_can_use_old_main_layout_and_partial_package_fails(self):
        self.assertTrue(hasattr(release_acceptance, 'verify_release_installation'),
                        'verified historical installation layouts must be distinguished explicitly')
        package = self.package(media=False, sha=LEGACY_SHA)
        install, notices = self.root / 'installed', self.root / 'old main notices'
        install.mkdir()
        shutil.copytree(package, notices)
        shutil.copy2(package / 'rarus-echo', install / 'rarus-echo')
        release_acceptance.verify_release_installation(install, notices, package, 'linux-amd64',
                                                       VERSION, LEGACY_SHA, require_media=False)
        immutable = install / '.rarus-echo/packages' / (VERSION + '-' + LEGACY_SHA)
        immutable.mkdir(parents=True)
        with self.assertRaisesRegex(ValueError, 'Installed file differs|Versioned package'):
            release_acceptance.verify_release_installation(install, notices, package, 'linux-amd64',
                                                           VERSION, LEGACY_SHA, require_media=False)
        with self.assertRaisesRegex(ValueError, 'approved published legacy'):
            release_acceptance.verify_release_installation(install, notices, package, 'linux-amd64',
                                                           VERSION, 'b' * 40, require_media=False)

    def test_new_release_cannot_fall_back_to_old_main_notice_layout(self):
        self.assertTrue(hasattr(release_acceptance, 'verify_release_installation'))
        package = self.package()
        install, notices = self.root / 'installed', self.root / 'old main notices'
        install.mkdir()
        shutil.copytree(package, notices)
        shutil.copy2(package / 'rarus-echo', install / 'rarus-echo')
        with self.assertRaisesRegex(ValueError, 'Installed file differs|Versioned package'):
            release_acceptance.verify_release_installation(install, notices, package, 'linux-amd64',
                                                           VERSION, SHA, require_media=True)

    def test_seeds_private_default_and_unicode_json_and_detects_configuration_changes(self):
        env = release_acceptance.isolated_environment(self.root)
        target = "windows-amd64" if os.name == "nt" else "linux-amd64"
        with release_acceptance.configuration_fixture(self.root, env, target) as configs:
            default, explicit = configs
            base = Path(env["APPDATA"] if os.name == "nt" else env["XDG_CONFIG_HOME"])
            self.assertEqual(base / "rarus-echo/config.json", default)
            self.assertIn("параметры доступа", str(explicit))
            for path, original in configs.items():
                data = json.loads(original)
                self.assertEqual(1, data["version"])
                self.assertTrue(data["base_url"].startswith("http://127.0.0.1:"))
                if os.name != "nt":
                    self.assertEqual(0o600, path.stat().st_mode & 0o777)
                    self.assertEqual(0o700, path.parent.stat().st_mode & 0o777)
            release_acceptance.verify_files_unchanged(configs, "Configuration")
            explicit.write_bytes(b"installer destroyed the existing configuration")
            with self.assertRaisesRegex(ValueError, "Configuration changed"):
                release_acceptance.verify_files_unchanged(configs, "Configuration")

    @unittest.skipIf(os.name == "nt", "The offline stand-in executable uses a POSIX shebang")
    def test_configuration_acceptance_requires_real_http_from_both_saved_files(self):
        env = release_acceptance.isolated_environment(self.root)
        elsewhere = self.root / "another working directory"
        elsewhere.mkdir()
        binary = self.root / "fixture CLI"
        binary.write_text("#!" + sys.executable + "\n" + '''import json, os, sys, urllib.request
args = sys.argv[1:]
if "--config" in args:
    path = args[args.index("--config") + 1]
else:
    path = os.path.join(os.environ["XDG_CONFIG_HOME"], "rarus-echo", "config.json")
with open(path, encoding="utf-8") as stream:
    config = json.load(stream)
request = urllib.request.Request(config["base_url"] + "/v1/queue", headers={
    "Authorization": config["api_key"], "user-id": config["user_id"]})
with urllib.request.urlopen(request) as response:
    print(json.dumps(json.load(response)["results"][0]))
''', encoding="utf-8")
        binary.chmod(0o755)
        with release_acceptance.configuration_fixture(self.root, env, "linux-amd64") as configs:
            release_acceptance.check_configuration(binary, elsewhere, env, configs)
            binary.write_text("#!" + sys.executable + "\nprint('{\"files_count\": 3}')\n", encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "configuration HTTP"):
                release_acceptance.check_configuration(binary, elsewhere, env, configs)

    def test_detects_shell_profile_changes_with_no_modify_path(self):
        env = release_acceptance.isolated_environment(self.root)
        with release_acceptance.preserved_user_state(env, "linux-amd64") as check:
            check()
            profile = Path(env["HOME"]) / ".bash_profile"
            self.assertIn(b"sentinel", profile.read_bytes())
            profile.write_bytes(profile.read_bytes() + b"installer changed PATH\n")
            with self.assertRaisesRegex(ValueError, "Shell profile changed"):
                check()

    def test_restores_windows_registry_path_after_detected_change_or_installer_failure(self):
        original = {"exists": True, "value": "%USERPROFILE%\\Original;C:\\Tools", "kind": "ExpandString"}
        state = original.copy()

        def registry(env, restore=None):
            if restore is not None:
                state.clear()
                state.update(restore)
            return state.copy()

        with patch.object(release_acceptance, "windows_user_path", side_effect=registry):
            with self.assertRaisesRegex(ValueError, "User PATH changed"):
                with release_acceptance.preserved_user_state({}, "windows-amd64") as check:
                    state["value"] = "installer accidentally changed the registry"
                    check()
            self.assertEqual(original, state)
            with self.assertRaisesRegex(RuntimeError, "installer failure"):
                with release_acceptance.preserved_user_state({}, "windows-amd64"):
                    state["value"] = "installer changed PATH then crashed"
                    raise RuntimeError("installer failure")
            self.assertEqual(original, state)


if __name__ == "__main__":
    unittest.main()
