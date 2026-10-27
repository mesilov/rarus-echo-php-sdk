#!/usr/bin/env python3
"""Run the extracted CLI with a clean PATH and a local API, never real credentials."""
import argparse
import http.server
import json
import os
from pathlib import Path
import shutil
import struct
import subprocess
import sys
import tarfile
import tempfile
import threading
import wave
import zipfile
import signal
import time
import hashlib
import email.parser
import email.policy

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from release_assets import inspect_archive, validate_legacy_identity
from media import toolkit as media_toolkit

FILE_ID = "33333333-3333-4333-8333-333333333333"
API_KEY = "11111111-1111-4111-8111-111111111111"
USER_ID = "22222222-2222-4222-8222-222222222222"


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def command(binary, args, cwd, env, expected=0, php=None):
    prefix = [str(php)] if php else []
    result = subprocess.run(prefix + [str(binary), *args], cwd=cwd, env=env,
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30,
                            encoding="utf-8", errors="replace")
    check(result.returncode == expected,
          str(args) + ": exit " + str(result.returncode) + "\n" + result.stdout + result.stderr)
    return result


class Handler(http.server.BaseHTTPRequestHandler):
    requests = []

    def log_message(self, *args):
        pass

    def answer(self, body):
        data = json.dumps(body).encode()
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        self.requests.append((self.path, dict(self.headers), b""))
        self.answer({"results": [{"files_count": 3, "files_size": 1, "files_duration": 2}]})

    def do_POST(self):
        body = self.rfile.read(int(self.headers.get("Content-Length", "0")))
        self.requests.append((self.path, dict(self.headers), body))
        self.answer({"results": [{"file_id": FILE_ID}]})


def smoke(directory, version, sha, probe=None, php=None, doctor=True, media=True):
    if not media:
        validate_legacy_identity(version, sha)
    with tempfile.TemporaryDirectory(prefix="echo-acceptance-") as temp:
        workspace = Path(temp) / "изолированный каталог"
        workspace.mkdir()
        suffix = ".phar" if php else (".exe" if os.name == "nt" else "")
        binary = workspace / ("rarus-echo" + suffix)
        if php or not media:
            shutil.copy2(Path(directory) / binary.name, binary)
        else:
            shutil.copytree(directory, workspace, dirs_exist_ok=True)
            metadata = json.loads((workspace / "media-build-info.json").read_text())
            media_toolkit.verify_payload(workspace, metadata["target"])
            subprocess.run([sys.executable, Path(__file__).resolve().parents[1] / "media/accept.py",
                            workspace, "--target", metadata["target"]], check=True)
        binary.chmod(0o755)
        env = {k: v for k, v in os.environ.items()
               if not k.startswith("RARUS_ECHO_") and k.lower() not in
               {"path", "php_ini_scan_dir", "phprc", "http_proxy", "https_proxy", "all_proxy"}}
        temporary = workspace / "temporary"
        temporary.mkdir()
        env.update({"PATH": "", "HOME": str(workspace), "USERPROFILE": str(workspace),
                    "APPDATA": str(workspace / "Roaming"), "XDG_CONFIG_HOME": str(workspace / "config home"),
                    "TMP": str(temporary), "TEMP": str(temporary), "TMPDIR": str(temporary),
                    "NO_PROXY": "*", "no_proxy": "*"})
        elsewhere = workspace / "another working directory"
        elsewhere.mkdir()
        result = command(binary, ["--version", "--no-ansi"], elsewhere, env, php=php)
        check(version in result.stdout and sha in result.stdout, "Embedded identity mismatch")
        command(binary, ["--help", "--no-ansi"], workspace, env, php=php)
        for name in ["install", "queue", "submit", "status", "transcript"] + (["doctor"] if doctor else []):
            command(binary, [name, "--help", "--no-ansi"], workspace, env, php=php)
        missing = command(binary, ["queue", "--json", "--no-ansi"], workspace, env, expected=1, php=php)
        check("RARUS_ECHO_API_KEY" in missing.stderr, "Missing credential handling failed")
        if doctor:
            diagnosis = command(binary, ["doctor", "--json", "--no-ansi"], workspace, env, expected=1, php=php)
            report = json.loads(diagnosis.stdout)
            check(report["schema_version"] == 1 and report["status"] == "error", "Doctor report contract failed")
            check(set(report["checks"]) == {"config", "connection", "ffmpeg"}, "Doctor omitted a check")
            check(report["checks"]["connection"]["status"] == "skipped", "Doctor requested Echo without credentials")
            if media and not php:
                ffmpeg = report["checks"]["ffmpeg"]
                expected_path = workspace / 'libexec' / ('ffmpeg.exe' if os.name == 'nt' else 'ffmpeg')
                media_info = json.loads((workspace / 'media-build-info.json').read_text())
                check(ffmpeg['status'] == 'ok' and ffmpeg['code'] == 'ffmpeg_ok'
                      and Path(ffmpeg['details']['path']).resolve() == expected_path.resolve()
                      and ffmpeg['details']['version'] == media_info['ffmpeg_version'],
                      "Doctor did not validate the pinned bundled toolkit")
            else:
                check(report["checks"]["ffmpeg"]["code"] == "ffmpeg_missing", "Doctor ignored isolated PATH")
        invalid = command(binary, ["status", "invalid-id", "--json", "--no-ansi"],
                          workspace, env, expected=1, php=php)
        check("valid UUID" in invalid.stderr, "Invalid input handling failed")
        if probe:
            copied_probe = workspace / ("runtime-probe" + suffix)
            shutil.copy2(probe, copied_probe)
            copied_probe.chmod(0o755)
            result = command(copied_probe, [], workspace, env, php=php)
            info = json.loads(result.stdout)
            metadata = json.loads((Path(directory) / "build-info.json").read_text())
            check(info["php_version"] == metadata["php_version"], "Compiled PHP version mismatch")
            check(info["lock_sha256"] == metadata["composer_lock_sha256"], "Embedded lock differs")
            check(version in info["version"] and sha in info["version"], "Probe identity differs")
            if not php:
                check(Path(info["native_executable"]).resolve() == copied_probe.resolve(),
                      "Runtime probe lost the physical native executable path")
                if os.name == "nt":
                    check(info["windows_ctrl_handler"], "Native Windows console-control API is unavailable")
        audio = workspace / "аудио запись.wav"
        with wave.open(str(audio), "wb") as wav:
            wav.setnchannels(1)
            wav.setsampwidth(2)
            wav.setframerate(8000)
            wav.writeframes(struct.pack("<h", 1) * 800)
        if probe and not php and media:
            cancellation_check(copied_probe, workspace / "libexec" / ("ffmpeg.exe" if os.name == "nt" else "ffmpeg"),
                               audio, workspace, env)
            timeout_check(copied_probe, workspace / "libexec" / ("ffmpeg.exe" if os.name == "nt" else "ffmpeg"),
                          audio, workspace, env)
        Handler.requests = []
        server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        server.daemon_threads = True
        worker = threading.Thread(target=server.serve_forever, daemon=True)
        worker.start()
        try:
            url = "http://127.0.0.1:" + str(server.server_port)
            credentials = {"RARUS_ECHO_API_KEY": API_KEY, "RARUS_ECHO_USER_ID": USER_ID,
                           "RARUS_ECHO_BASE_URL": url}
            queue = command(binary, ["queue", "--json", "--no-ansi"], workspace, env | credentials, php=php)
            check(json.loads(queue.stdout)["files_count"] == 3, "Queue or environment credentials failed")
            if doctor:
                diagnosis = command(binary, ["doctor", "--json", "--no-ansi"], workspace,
                                    env | credentials, expected=0 if media and not php else 1, php=php)
                report = json.loads(diagnosis.stdout)
                if media and not php:
                    check(report['status'] == 'warning' and report['checks']['config']['status'] == 'warning'
                          and report['checks']['ffmpeg']['status'] == 'ok',
                          "Bundled Doctor must succeed with a missing-file warning and environment credentials")
                check(report["checks"]["connection"]["status"] == "ok", "Doctor authenticated queue request failed")
                check(report["checks"]["config"]["details"]["source"] == "environment", "Doctor source selection failed")
                check(API_KEY not in diagnosis.stdout + diagnosis.stderr
                      and USER_ID not in diagnosis.stdout + diagnosis.stderr, "Doctor leaked credentials")
            config = workspace / "параметры доступа" / "connection.json"
            command(binary, ["--config", str(config), "--help"], workspace, env, php=php)
            command(binary, ["--config", str(config), "--version"], workspace, env, php=php)
            command(binary, ["install", "--config", str(config), "--no-interaction"],
                    workspace, env, expected=2, php=php)
            check(not config.exists(), "Noninteractive install created a file")
            if probe:
                command(copied_probe, ["configure-fixture", "default", url], workspace, env, php=php)
                command(copied_probe, ["configure-fixture", str(config), url], workspace, env, php=php)
                command(copied_probe, ["configure-fixture", str(config), url], workspace, env, php=php)
                elsewhere = workspace / "another directory"
                elsewhere.mkdir()
                queue = command(binary, ["queue", "--json"], elsewhere, env, php=php)
                check(json.loads(queue.stdout)["files_count"] == 3, "Default configuration depends on cwd")
                invalid_env = env | {"RARUS_ECHO_API_KEY": "invalid-ambient-key"}
                queue = command(binary, ["--config", str(config), "queue", "--json"],
                                elsewhere, invalid_env, php=php)
                check(json.loads(queue.stdout)["files_count"] == 3, "Explicit configuration did not isolate credentials")
                queue = command(binary, ["queue", "--config", str(config.relative_to(workspace)), "--json"],
                                workspace, env, php=php)
                check(json.loads(queue.stdout)["files_count"] == 3, "Relative/Unicode configuration path failed")
                missing_path = str(workspace / "missing.json")
                command(binary, ["queue", "--config", missing_path, "--json"], workspace,
                        env | credentials, expected=1, php=php)
                config.write_text('{"api_key":"RecognizableFixtureSecret"', encoding="utf-8")
                malformed = command(binary, ["queue", "--config", str(config), "--json"],
                                    workspace, env, expected=1, php=php)
                check("RecognizableFixtureSecret" not in malformed.stdout + malformed.stderr,
                      "Malformed configuration leaked contents")
                if os.name != "nt":
                    check(config.stat().st_mode & 0o777 == 0o600, "Configuration is not private")
                    check(config.parent.stat().st_mode & 0o777 == 0o700, "Configuration directory is not private")
            (workspace / ".env").write_text("".join(k + "=" + v + "\n" for k, v in credentials.items()), encoding="utf-8")
            if doctor and media and not php:
                diagnosis = command(binary, ["doctor", "--json", "--no-ansi"], workspace, env, php=php)
                report = json.loads(diagnosis.stdout)
                expected_status = 'ok' if probe else 'warning'
                check(report["status"] == expected_status and report["checks"]["ffmpeg"]["status"] == "ok"
                      and report["checks"]["connection"]["status"] == "ok", "Configured bundled Doctor failed")
            upload = command(binary, ["submit", str(audio), "--json", "--no-ansi"], workspace, env, php=php)
            check(json.loads(upload.stdout)["file_ids"] == [FILE_ID], "Upload or dotenv failed")
            request = Handler.requests[-1]
            check(request[0] == "/v1/async/transcription"
                  and audio.read_bytes() in request[2], "Multipart upload bytes/path mismatch")
            if not php and media:
                fixtures = Path(__file__).resolve().parents[2] / "tests/Fixtures/Media"
                fixture = fixtures / "video-aac.mp4"
                check(fixture.is_file(), "Committed media fixtures are required for native acceptance")
                video = workspace / "видео с пробелами $ literal.mp4"
                shutil.copy2(fixture, video)
                original_hash = hashlib.sha256(video.read_bytes()).hexdigest()
                before = len(Handler.requests)
                prepared = command(binary, ["submit", str(video), "--json", "--no-ansi"],
                                   elsewhere, env | credentials, php=php)
                check(json.loads(prepared.stdout)["file_ids"] == [FILE_ID], "Video preparation/upload failed")
                check(len(Handler.requests) == before + 1, "Unexpected request during video preparation")
                multipart = Handler.requests[-1][2]
                check(video.read_bytes() not in multipart, "Original video was uploaded")
                content_type = Handler.requests[-1][1]["Content-Type"]
                message = email.parser.BytesParser(policy=email.policy.default).parsebytes(
                    ("Content-Type: " + content_type + "\r\nMIME-Version: 1.0\r\n\r\n").encode() + multipart)
                files = [part for part in message.iter_parts() if part.get_filename()]
                check(len(files) == 1, "Prepared multipart does not contain exactly one file")
                received = workspace / "received-audio"
                received.write_bytes(files[0].get_payload(decode=True))
                tool = workspace / "libexec" / ("ffprobe.exe" if os.name == "nt" else "ffprobe")
                streams = json.loads(command(tool, ["-v", "error", "-show_streams", "-of", "json", str(received)],
                                             elsewhere, env).stdout)["streams"]
                check(len(streams) == 1 and streams[0]["codec_type"] == "audio"
                      and streams[0]["sample_rate"] == "48000" and streams[0]["channels"] == 2,
                      "Uploaded media did not preserve audio-only channel/rate contract")
                check(hashlib.sha256(video.read_bytes()).hexdigest() == original_hash, "Source video changed")
                check(not list(temporary.glob("rarus-echo-media-*")), "Prepared temporary files were not cleaned")
            check(API_KEY not in upload.stdout + upload.stderr
                  and USER_ID not in upload.stdout + upload.stderr, "Credentials leaked to output")
        finally:
            server.shutdown()
            server.server_close()
            worker.join(timeout=5)
        check(not list(temporary.glob("rarus-echo-payload-*")), "Temporary PHAR was not removed")
        print("Accepted standalone CLI: " + version + " " + sha)


def child_pids(pid):
    if os.name == 'nt':
        powershell = Path(os.environ['SystemRoot']) / 'System32/WindowsPowerShell/v1.0/powershell.exe'
        output = subprocess.check_output([str(powershell), '-NoProfile', '-Command',
            "(Get-CimInstance Win32_Process -Filter 'ParentProcessId = " + str(pid) + "').ProcessId"], text=True)
        return [int(value) for value in output.split()]
    if sys.platform.startswith('linux'):
        return [int(value) for value in Path('/proc/' + str(pid) + '/task/' + str(pid) + '/children').read_text().split()]
    output = subprocess.check_output(['/bin/ps', '-axo', 'pid=,ppid='], text=True)
    return [int(row.split()[0]) for row in output.splitlines() if len(row.split()) == 2 and int(row.split()[1]) == pid]


def cancellation_check(probe, ffmpeg, audio, workspace, env):
    owned, ready = workspace / 'cancellation-owned', workspace / 'cancellation-ready'
    kwargs = {'creationflags': subprocess.CREATE_NEW_CONSOLE | subprocess.CREATE_NEW_PROCESS_GROUP} if os.name == 'nt' else {}
    process = subprocess.Popen([str(probe), 'media-cancel-fixture', str(ffmpeg), str(audio), str(owned), str(ready)],
                               cwd=workspace, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, **kwargs)
    children, handles = [], []
    try:
        deadline = time.monotonic() + 10
        while not ready.exists() and process.poll() is None and time.monotonic() < deadline:
            time.sleep(0.05)
        check(ready.exists() and process.poll() is None, 'Native cancellation fixture never became ready')
        children = child_pids(process.pid)
        check(len(children) == 1, 'Cancellation fixture must own exactly one running media process')
        if os.name == 'nt':
            import ctypes
            kernel = ctypes.WinDLL('kernel32', use_last_error=True)
            kernel.OpenProcess.restype = ctypes.c_void_p
            kernel.FreeConsole()
            check(kernel.AttachConsole(process.pid), 'Cannot attach to the native cancellation console')
            for pid in children:
                handle = kernel.OpenProcess(0x00100000, False, pid)
                check(handle, 'Cannot inspect the running native media process')
                handles.append(handle)
            check(kernel.GenerateConsoleCtrlEvent(1, process.pid), 'Cannot deliver native CTRL_BREAK')
        else:
            process.send_signal(signal.SIGINT)
        stdout, stderr = process.communicate(timeout=8)
        check(process.returncode == 0 and json.loads(stdout)['cancelled'],
              'Native cancellation handler/runner failed: ' + stderr.decode(errors='replace'))
        if os.name == 'nt':
            for handle in handles:
                check(kernel.WaitForSingleObject(ctypes.c_void_p(handle), 5000) == 0,
                      'Native Windows cancellation left the media process running')
        else:
            for pid in children:
                try:
                    os.kill(pid, 0)
                except ProcessLookupError:
                    pass
                else:
                    raise RuntimeError('Cancellation left the media process running')
        check(not owned.exists(), 'Cancellation retained partial owned output')
        check(not list(Path(env['TMPDIR']).glob('rarus-echo-process-*')), 'Cancellation retained media process files')
    finally:
        if process.poll() is None:
            process.kill()
            process.communicate(timeout=5)
        if os.name == 'nt' and handles:
            for handle in handles:
                kernel.CloseHandle(ctypes.c_void_p(handle))
            kernel.FreeConsole()
            kernel.AttachConsole(0xffffffff)
        ready.unlink(missing_ok=True)


def timeout_check(probe, ffmpeg, audio, workspace, env):
    owned, ready = workspace / 'timeout-owned', workspace / 'timeout-ready'
    process = subprocess.Popen([str(probe), 'media-timeout-fixture', str(ffmpeg), str(audio), str(owned), str(ready)],
                               cwd=workspace, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    handles = []
    started = time.monotonic()
    try:
        deadline = started + 5
        while not ready.exists() and process.poll() is None and time.monotonic() < deadline:
            time.sleep(0.05)
        check(ready.exists() and process.poll() is None, 'Native timeout fixture never started active FFmpeg')
        children = child_pids(process.pid)
        check(len(children) == 1, 'Timeout fixture must own exactly one running media process before the deadline')
        if os.name == 'nt':
            import ctypes
            kernel = ctypes.WinDLL('kernel32', use_last_error=True)
            kernel.OpenProcess.restype = ctypes.c_void_p
            for pid in children:
                handle = kernel.OpenProcess(0x00100000, False, pid)
                check(handle, 'Cannot inspect the media process before its timeout')
                handles.append(handle)
        stdout, stderr = process.communicate(timeout=8)
        check(process.returncode == 0, 'Native timeout fixture failed: ' + stderr.decode(errors='replace'))
        result = json.loads(stdout)
        check(result['timed_out'] and result['active_before_deadline'], 'FFmpeg did not reach the active-process deadline')
        check(5.5 <= result['elapsed_seconds'] <= 8 and time.monotonic() - started <= 9,
              'Native timeout did not respect its bounded six-second deadline')
        if os.name == 'nt':
            for handle in handles:
                check(kernel.WaitForSingleObject(ctypes.c_void_p(handle), 3000) == 0,
                      'Native Windows timeout left FFmpeg running')
        else:
            for pid in children:
                try:
                    os.kill(pid, 0)
                except ProcessLookupError:
                    pass
                else:
                    raise RuntimeError('Native timeout left FFmpeg running')
        check(not owned.exists(), 'Timeout retained partial owned audio')
        check(not list(Path(env['TMPDIR']).glob('rarus-echo-process-*')), 'Timeout retained private process logs/files')
    finally:
        if process.poll() is None:
            process.kill()
            process.communicate(timeout=5)
        if os.name == 'nt':
            for handle in handles:
                kernel.CloseHandle(ctypes.c_void_p(handle))
        ready.unlink(missing_ok=True)


def extract_archive(path, destination, version, sha, *, require_media=True):
    if path.suffix == ".zip":
        with zipfile.ZipFile(path) as archive:
            info = json.loads(archive.read("build-info.json"))
        inspect_archive(path, info["target"], version, sha, require_media=require_media)
        with zipfile.ZipFile(path) as archive:
            for name in archive.namelist():
                output = destination / name
                output.parent.mkdir(parents=True, exist_ok=True)
                with archive.open(name) as source, output.open("xb") as target:
                    shutil.copyfileobj(source, target)
    else:
        with tarfile.open(path) as archive:
            info = json.load(archive.extractfile("build-info.json"))
        inspect_archive(path, info["target"], version, sha, require_media=require_media)
        with tarfile.open(path) as archive:
            for member in archive:
                output = destination / member.name
                output.parent.mkdir(parents=True, exist_ok=True)
                with archive.extractfile(member) as source, output.open("xb") as target:
                    shutil.copyfileobj(source, target)
                output.chmod(member.mode)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("artifact", type=Path)
    parser.add_argument("--version", required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--probe", type=Path)
    parser.add_argument("--php", type=Path)
    parser.add_argument("--without-doctor", action="store_true",
                        help="Accept a historical release whose tagged source predates doctor")
    parser.add_argument("--without-media", action="store_true",
                        help="Accept only the frozen published release whose verified source predates media")
    args = parser.parse_args()
    if args.artifact.is_dir():
        smoke(args.artifact, args.version, args.source_sha, args.probe, args.php, not args.without_doctor, not args.without_media)
        return
    with tempfile.TemporaryDirectory(prefix="echo-extract-") as temp:
        destination = Path(temp)
        extract_archive(args.artifact, destination, args.version, args.source_sha, require_media=not args.without_media)
        smoke(destination, args.version, args.source_sha, args.probe, args.php, not args.without_doctor, not args.without_media)


if __name__ == "__main__":
    main()
