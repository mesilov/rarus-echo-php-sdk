#!/usr/bin/env python3
"""Accept a public release with its native installer, without real API credentials."""
import argparse
import base64
from contextlib import contextmanager
import hashlib
import http.server
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import sys
import tempfile
import threading
import urllib.parse
import urllib.request

from release_assets import TARGETS, archive_name, executable, inspect_archive

BUILD = Path(__file__).resolve().parent
ROOT = BUILD.parent
PUBLIC_REPOSITORY = "https://github.com/mesilov/rarus-echo-php-sdk"
PUBLIC_MAIN = "https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/"
sys.path.insert(0, str(BUILD / "tests"))
from smoke import API_KEY, USER_ID, Handler, command as smoke_command, extract_archive


def validate_identity(version, sha):
    if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._+-]{0,127}", version) or ".." in version:
        raise ValueError("Invalid release tag")
    if not re.fullmatch(r"[0-9a-f]{40}", sha):
        raise ValueError("Expected a full lowercase source SHA")


def native_target(system=None, machine=None):
    system, machine = system or platform.system(), (machine or platform.machine()).lower()
    targets = {("Linux", "x86_64"): "linux-amd64", ("Linux", "amd64"): "linux-amd64",
               ("Linux", "aarch64"): "linux-arm64", ("Linux", "arm64"): "linux-arm64",
               ("Darwin", "arm64"): "macos-arm64", ("Windows", "amd64"): "windows-amd64"}
    if (system, machine) not in targets:
        raise ValueError("Unsupported native platform: " + system + "/" + machine)
    return targets[system, machine]


def resolve_main_tag(root, version, expected_sha=None):
    validate_identity(version, expected_sha or "0" * 40)
    result = subprocess.run(["git", "rev-parse", "--verify", "refs/tags/" + version + "^{commit}"],
                            cwd=root, capture_output=True, text=True, timeout=30)
    if result.returncode:
        raise ValueError("Release tag does not resolve to a commit: " + version)
    sha = result.stdout.strip()
    validate_identity(version, sha)
    if expected_sha is not None and sha != expected_sha:
        raise ValueError("Release tag differs from the expected source SHA")
    result = subprocess.run(["git", "merge-base", "--is-ancestor", sha, "origin/main"],
                            cwd=root, capture_output=True, timeout=30)
    if result.returncode:
        raise ValueError("Release tag is not in origin/main history")
    return sha


def file_hash(path):
    with Path(path).open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def verify_archive(archive, manifest, target, version, sha):
    records = {}
    for line in Path(manifest).read_text(encoding="utf-8").splitlines():
        match = re.fullmatch(r"([0-9a-fA-F]{64}) [ *]([^\s]+)", line)
        if match is None or match[2] in records:
            raise ValueError("Invalid or duplicate checksum manifest record")
        records[match[2]] = match[1].lower()
    if set(records) != {archive_name(candidate) for candidate in TARGETS}:
        raise ValueError("Invalid checksum manifest: expected all four release archives")
    if file_hash(archive) != records[Path(archive).name]:
        raise ValueError("Release archive checksum differs: " + Path(archive).name)
    inspect_archive(archive, target, version, sha)


def download(url, destination):
    # No GitHub API, token, netrc, credential helper or workflow artifact fallback.
    with urllib.request.urlopen(url, timeout=300) as response:
        if urllib.parse.urlsplit(response.geturl()).scheme != "https":
            raise ValueError("Public download must remain on HTTPS")
        with Path(destination).open("xb") as output:
            shutil.copyfileobj(response, output)


def verify_installer(public, source):
    if Path(public).read_bytes() != Path(source).read_bytes():
        raise ValueError("Public main installer differs from the checked out source")


def isolated_environment(workspace, inherited=None):
    env = {key: value for key, value in (os.environ if inherited is None else inherited).items()
           if not key.upper().startswith("RARUS_ECHO_")}
    home = workspace / "дом пользователя"
    temporary = workspace / "temporary files"
    directories = {"HOME": home, "USERPROFILE": home, "APPDATA": home / "Roaming",
                   "LOCALAPPDATA": home / "Local", "XDG_CONFIG_HOME": home / "config home",
                   "ZDOTDIR": home, "TMP": temporary, "TEMP": temporary, "TMPDIR": temporary}
    for key, path in directories.items():
        path.mkdir(parents=True, exist_ok=True)
        env[key] = str(path)
    return env


def verify_installation(install, notices, verified, target):
    binary = executable(target)
    for source in verified.rglob("*"):
        if source.is_file():
            relative = source.relative_to(verified)
            installed = install / binary if relative.as_posix() == binary else notices / relative
            if not installed.is_file() or file_hash(installed) != file_hash(source):
                raise ValueError("Installed file differs from verified release: " + str(relative))


def verify_files_unchanged(snapshot, label):
    for path, original in snapshot.items():
        if not path.is_file() or path.read_bytes() != original:
            raise ValueError(label + " changed: " + str(path))


def run_windows_script(script, env):
    script = "$ErrorActionPreference = 'Stop'\n[Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)\n" + script
    encoded = base64.b64encode(script.encode("utf-16le")).decode("ascii")
    powershell = Path(os.environ["SystemRoot"]) / "System32/WindowsPowerShell/v1.0/powershell.exe"
    return subprocess.run([str(powershell), "-NoProfile", "-NonInteractive", "-EncodedCommand", encoded],
                          env=env, check=True, capture_output=True, encoding="utf-8", timeout=30).stdout.strip()


def protect_configuration(path, env, target):
    if not target.startswith("windows"):
        path.chmod(0o700 if path.is_dir() else 0o600)
        return
    # Match ConfigFileProtection.php: protected ACL, current-user owner and FullControl only.
    run_windows_script("""
$path = $env:ECHO_ACCEPTANCE_ACL_PATH
$owner = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
if ([System.IO.Directory]::Exists($path)) {
    $security = [System.Security.AccessControl.DirectorySecurity]::new()
    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($owner, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
} else {
    $security = [System.Security.AccessControl.FileSecurity]::new()
    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($owner, 'FullControl', 'Allow')
}
$security.SetOwner($owner)
$security.SetAccessRuleProtection($true, $false)
$security.AddAccessRule($rule)
if ([System.IO.Directory]::Exists($path)) { [System.IO.Directory]::SetAccessControl($path, $security) }
else { [System.IO.File]::SetAccessControl($path, $security) }
""", env | {"ECHO_ACCEPTANCE_ACL_PATH": str(path)})


@contextmanager
def configuration_fixture(workspace, env, target):
    Handler.requests = []
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
    server.daemon_threads = True
    worker = threading.Thread(target=server.serve_forever, daemon=True)
    worker.start()
    try:
        base = Path(env["APPDATA"] if target.startswith("windows") else env["XDG_CONFIG_HOME"])
        paths = [base / "rarus-echo/config.json", workspace / "параметры доступа/connection.json"]
        snapshot = {}
        for name, path in zip(("default", "explicit"), paths):
            path.parent.mkdir(parents=True, exist_ok=True)
            config = {"version": 1, "api_key": API_KEY, "user_id": USER_ID,
                      "base_url": "http://127.0.0.1:" + str(server.server_port) + "/" + name}
            snapshot[path] = (json.dumps(config, indent=2) + "\n").encode("utf-8")
            path.write_bytes(snapshot[path])
            protect_configuration(path.parent, env, target)
            protect_configuration(path, env, target)
        yield snapshot
    finally:
        server.shutdown()
        server.server_close()
        worker.join(timeout=5)


def check_configuration(binary, cwd, env, configs):
    verify_files_unchanged(configs, "Configuration")
    explicit = list(configs)[1]
    runtime_env = {key: value for key, value in env.items() if key.lower() not in
                   {"http_proxy", "https_proxy", "all_proxy", "php_ini_scan_dir", "phprc"}}
    runtime_env.update({"PATH": "", "NO_PROXY": "*", "no_proxy": "*"})
    for name, arguments in (("default", []), ("explicit", ["--config", str(explicit)])):
        selected_env = runtime_env
        if name == "explicit":
            selected_env = runtime_env | {"RARUS_ECHO_API_KEY": "invalid-ambient-key",
                                          "RARUS_ECHO_USER_ID": "invalid-ambient-user",
                                          "RARUS_ECHO_BASE_URL": "http://127.0.0.1:1"}
        before = len(Handler.requests)
        result = smoke_command(binary, arguments + ["queue", "--json", "--no-ansi"], cwd, selected_env)
        if json.loads(result.stdout).get("files_count") != 3 or len(Handler.requests) <= before:
            raise ValueError("Saved configuration HTTP check failed: " + name)
        path, headers, _ = Handler.requests[-1]
        headers = {key.lower(): value for key, value in headers.items()}
        if not path.startswith("/" + name + "/") or headers.get("authorization") != API_KEY or headers.get("user-id") != USER_ID:
            raise ValueError("Saved configuration HTTP identity differs: " + name)
    verify_files_unchanged(configs, "Configuration")


def windows_user_path(env, restore=None):
    if restore is not None:
        run_windows_script("""
$state = $env:ECHO_ACCEPTANCE_USER_PATH | ConvertFrom-Json
if ($state.exists) {
    $key = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey('Environment')
    try { $key.SetValue('Path', $state.value, [Microsoft.Win32.RegistryValueKind]$state.kind) }
    finally { $key.Dispose() }
} else {
    $key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey('Environment', $true)
    if ($null -ne $key) { try { $key.DeleteValue('Path', $false) } finally { $key.Dispose() } }
}
""", env | {"ECHO_ACCEPTANCE_USER_PATH": json.dumps(restore)})
    return json.loads(run_windows_script("""
$state = @{exists = $false; value = $null; kind = $null}
$key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey('Environment')
if ($null -ne $key) {
    try {
        if ($key.GetValueNames() -contains 'Path') {
            $state.exists = $true
            $state.value = $key.GetValue('Path', $null, [Microsoft.Win32.RegistryValueOptions]::DoNotExpandEnvironmentNames)
            $state.kind = $key.GetValueKind('Path').ToString()
        }
    } finally { $key.Dispose() }
}
$state | ConvertTo-Json -Compress
""", env))


@contextmanager
def preserved_user_state(env, target):
    if target.startswith("windows"):
        original = windows_user_path(env)

        def check():
            if windows_user_path(env) != original:
                raise ValueError("User PATH changed despite -NoModifyPath")

        try:
            yield check
        finally:
            if windows_user_path(env) != original:
                if windows_user_path(env, restore=original) != original:
                    raise ValueError("Could not restore the original User PATH")
    else:
        home = Path(env["HOME"])
        profiles = [home / name for name in (".bashrc", ".bash_profile", ".bash_login", ".profile")]
        profiles.append(Path(env["ZDOTDIR"]) / ".zshrc")
        snapshot = {}
        for path in profiles:
            snapshot[path] = b"# release acceptance profile sentinel\n"
            path.write_bytes(snapshot[path])
        yield lambda: verify_files_unchanged(snapshot, "Shell profile")


def accept_release(version, sha, target):
    resolve_main_tag(ROOT, version, sha)
    if target != native_target():
        raise ValueError("Requested target differs from the native runner")
    with tempfile.TemporaryDirectory(prefix="echo-public-release-") as temporary:
        workspace = Path(temporary) / "проверка релиза"
        workspace.mkdir()
        env = isolated_environment(workspace)
        cwd = workspace / "рабочая папка"
        cwd.mkdir()
        archive = workspace / archive_name(target)
        manifest = workspace / "SHA256SUMS"
        release_url = PUBLIC_REPOSITORY + "/releases/download/" + urllib.parse.quote(version, safe="") + "/"
        download(release_url + "SHA256SUMS", manifest)
        download(release_url + archive.name, archive)
        verify_archive(archive, manifest, target, version, sha)
        installer_name = "install.ps1" if target.startswith("windows") else "install.sh"
        installer = workspace / installer_name
        download(PUBLIC_MAIN + installer_name, installer)
        verify_installer(installer, ROOT / installer_name)
        # Reuse the existing validated extractor; never execute an unchecked archive.
        verified = workspace / "verified package"
        verified.mkdir()
        extract_archive(archive, verified, version, sha)
        install = workspace / "установленный CLI"
        if target.startswith("windows"):
            powershell = Path(os.environ["SystemRoot"]) / "System32/WindowsPowerShell/v1.0/powershell.exe"
            command = [str(powershell), "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass",
                       "-File", str(installer), "-Version", version, "-InstallDir", str(install), "-NoModifyPath"]
            notices = install / "package"
        else:
            command = ["sh", str(installer), "--version", version, "--install-dir", str(install), "--no-modify-path"]
            notices = Path(env["HOME"]) / ".local/share/rarus-echo/package"
        with configuration_fixture(workspace, env, target) as configs, preserved_user_state(env, target) as check_user_state:
            for attempt in ("fresh", "repeat"):
                print("Accepting " + attempt + " public installation for " + target, flush=True)
                subprocess.run(command, cwd=cwd, env=env, check=True, timeout=900)
                check_user_state()
                verify_files_unchanged(configs, "Configuration")
                verify_installation(install, notices, verified, target)
                check_configuration(install / executable(target), cwd, env, configs)
                subprocess.run([sys.executable, str(BUILD / "tests/smoke.py"), str(install),
                                "--version", version, "--source-sha", sha],
                               cwd=cwd, env=env, check=True, timeout=180)
                verify_files_unchanged(configs, "Configuration")
                check_user_state()
        print("Accepted public release: " + version + " " + sha + " " + target)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--version", required=True, help="Exact public release tag")
    parser.add_argument("--source-sha", help="Expected full commit SHA resolved from the tag")
    parser.add_argument("--target", choices=TARGETS, help="Expected native runner target")
    parser.add_argument("--resolve-source-sha", action="store_true", help="Only verify tag/main ancestry and print its SHA")
    args = parser.parse_args()
    try:
        if args.resolve_source_sha:
            print(resolve_main_tag(ROOT, args.version, args.source_sha))
        else:
            if args.source_sha is None or args.target is None:
                parser.error("acceptance requires --source-sha and --target")
            accept_release(args.version, args.source_sha, args.target)
    except (ValueError, OSError, RuntimeError, subprocess.SubprocessError) as error:
        parser.exit(1, "Release acceptance failed: " + str(error) + "\n")


if __name__ == "__main__":
    main()
