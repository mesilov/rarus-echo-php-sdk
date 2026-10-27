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

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from release_assets import inspect_archive

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


def smoke(directory, version, sha, probe=None, php=None):
    with tempfile.TemporaryDirectory(prefix="echo-acceptance-") as temp:
        workspace = Path(temp) / "изолированный каталог"
        workspace.mkdir()
        suffix = ".phar" if php else (".exe" if os.name == "nt" else "")
        binary = workspace / ("rarus-echo" + suffix)
        shutil.copy2(Path(directory) / binary.name, binary)
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
        result = command(binary, ["--version", "--no-ansi"], workspace, env, php=php)
        check(version in result.stdout and sha in result.stdout, "Embedded identity mismatch")
        command(binary, ["--help", "--no-ansi"], workspace, env, php=php)
        for name in ["install", "queue", "submit", "status", "transcript"]:
            command(binary, [name, "--help", "--no-ansi"], workspace, env, php=php)
        missing = command(binary, ["queue", "--json", "--no-ansi"], workspace, env, expected=1, php=php)
        check("RARUS_ECHO_API_KEY" in missing.stderr, "Missing credential handling failed")
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
        audio = workspace / "аудио запись.wav"
        with wave.open(str(audio), "wb") as wav:
            wav.setnchannels(1)
            wav.setsampwidth(2)
            wav.setframerate(8000)
            wav.writeframes(struct.pack("<h", 1) * 800)
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
            upload = command(binary, ["submit", str(audio), "--json", "--no-ansi"], workspace, env, php=php)
            check(json.loads(upload.stdout)["file_ids"] == [FILE_ID], "Upload or dotenv failed")
            request = Handler.requests[-1]
            check(request[0] == "/v1/async/transcription"
                  and audio.read_bytes() in request[2], "Multipart upload bytes/path mismatch")
            check(API_KEY not in upload.stdout + upload.stderr
                  and USER_ID not in upload.stdout + upload.stderr, "Credentials leaked to output")
        finally:
            server.shutdown()
            server.server_close()
            worker.join(timeout=5)
        check(not list(temporary.glob("rarus-echo-payload-*")), "Temporary PHAR was not removed")
        print("Accepted standalone CLI: " + version + " " + sha)


def extract_archive(path, destination, version, sha):
    if path.suffix == ".zip":
        with zipfile.ZipFile(path) as archive:
            info = json.loads(archive.read("build-info.json"))
        inspect_archive(path, info["target"], version, sha)
        with zipfile.ZipFile(path) as archive:
            for name in archive.namelist():
                output = destination / name
                output.parent.mkdir(parents=True, exist_ok=True)
                with archive.open(name) as source, output.open("xb") as target:
                    shutil.copyfileobj(source, target)
    else:
        with tarfile.open(path) as archive:
            info = json.load(archive.extractfile("build-info.json"))
        inspect_archive(path, info["target"], version, sha)
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
    args = parser.parse_args()
    if args.artifact.is_dir():
        smoke(args.artifact, args.version, args.source_sha, args.probe, args.php)
        return
    with tempfile.TemporaryDirectory(prefix="echo-extract-") as temp:
        destination = Path(temp)
        extract_archive(args.artifact, destination, args.version, args.source_sha)
        smoke(destination, args.version, args.source_sha, args.probe, args.php)


if __name__ == "__main__":
    main()
