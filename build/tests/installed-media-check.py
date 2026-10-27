#!/usr/bin/env python3
"""Exercise the installed native entrypoint, toolkit lookup and local audio upload."""
import argparse
from email import policy
from email.parser import BytesParser
import hashlib
import http.server
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import threading

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from media.console import configure_build_console

configure_build_console()


FILE_IDS = ['33333333-3333-4333-8333-333333333333', '44444444-4444-4444-8444-444444444444']


def accept(binary, fixtures):
    binary, fixtures = Path(binary).resolve(), Path(fixtures).resolve()
    with tempfile.TemporaryDirectory(prefix='echo installed проверка ') as temporary:
        workspace = Path(temporary)
        cwd = workspace / 'другой рабочий каталог'
        cwd.mkdir()
        storage = workspace / 'temporary'
        storage.mkdir()
        env = {k: v for k, v in os.environ.items() if not k.upper().startswith('RARUS_ECHO_')
               and k.lower() not in {'path', 'phprc', 'php_ini_scan_dir', 'http_proxy', 'https_proxy', 'all_proxy'}}
        env.update({'PATH': '', 'HOME': str(workspace), 'USERPROFILE': str(workspace),
                    'APPDATA': str(workspace / 'Roaming'), 'XDG_CONFIG_HOME': str(workspace / 'config'),
                    'TMP': str(storage), 'TEMP': str(storage), 'TMPDIR': str(storage),
                    'NO_PROXY': '*', 'no_proxy': '*'})
        version = subprocess.run([str(binary), '--version', '--no-ansi'], cwd=cwd, env=env,
                                 check=True, capture_output=True, text=True, timeout=30)
        identity = re.fullmatch(r'RARUS Echo CLI ([A-Za-z0-9._+-]+) \(source ([0-9a-f]{40})\)',
                                version.stdout.strip())
        if not identity:
            raise AssertionError('Installed CLI has no valid native version/source identity')
        package = binary.parent / '.rarus-echo/packages' / (identity[1] + '-' + identity[2])
        suffix = '.exe' if os.name == 'nt' else ''
        probe = package / 'libexec' / ('ffprobe' + suffix)
        if not probe.is_file():
            raise AssertionError('Installed matching media package is absent')

        captured = []

        class Handler(http.server.BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass

            def do_POST(self):
                length = int(self.headers.get('Content-Length', '0'))
                if self.path != '/v1/async/transcription' or not 0 < length < 4 * 1024 * 1024:
                    self.send_error(400)
                    return
                header = ('Content-Type: ' + self.headers.get('Content-Type', '') +
                          '\r\nMIME-Version: 1.0\r\n\r\n').encode()
                message = BytesParser(policy=policy.default).parsebytes(header + self.rfile.read(length))
                parts = [part for part in message.iter_parts() if part.get_filename() is not None]
                captured.append([(part.get_filename(), part.get_payload(decode=True)) for part in parts])
                result = json.dumps({'results': [{'file_id': value} for value in FILE_IDS]}).encode()
                self.send_response(201)
                self.send_header('Content-Type', 'application/json')
                self.send_header('Content-Length', str(len(result)))
                self.end_headers()
                self.wfile.write(result)

        server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        worker = threading.Thread(target=server.serve_forever, daemon=True)
        worker.start()
        sources = []
        for index, name in enumerate(['video-aac.mp4', 'delayed.mp4']):
            source = cwd / ('видео $ literal ' + str(index) + '.mp4')
            shutil.copyfile(fixtures / name, source)
            sources.append(source)
        hashes = [hashlib.sha256(path.read_bytes()).hexdigest() for path in sources]
        credentials = {'RARUS_ECHO_API_KEY': '11111111-1111-4111-8111-111111111111',
                       'RARUS_ECHO_USER_ID': '22222222-2222-4222-8222-222222222222',
                       'RARUS_ECHO_BASE_URL': 'http://127.0.0.1:' + str(server.server_port)}
        try:
            result = subprocess.run([str(binary), 'submit', *map(str, sources), '--json', '--no-ansi'],
                                    cwd=cwd, env=env | credentials, capture_output=True,
                                    encoding='utf-8', errors='replace', timeout=120)
            if result.returncode != 0:
                raise AssertionError('Installed video submit failed: ' + result.stdout + result.stderr)
        finally:
            server.shutdown()
            server.server_close()
            worker.join(timeout=5)
        if json.loads(result.stdout) != {'file_ids': FILE_IDS}:
            raise AssertionError('Installed video submit changed JSON stdout')
        if len(captured) != 1 or len(captured[0]) != 2:
            raise AssertionError('Installed video batch was not one ordered multipart request')
        for index, ((filename, data), expected_codec) in enumerate(zip(captured[0], ['aac', 'flac'])):
            if data == sources[index].read_bytes() or filename.lower().endswith('.mp4'):
                raise AssertionError('Installed CLI uploaded original video instead of prepared audio')
            uploaded = workspace / ('uploaded-' + str(index) + Path(filename).suffix)
            uploaded.write_bytes(data)
            response = subprocess.run([str(probe), '-v', 'error', '-show_streams', '-of', 'json', str(uploaded)],
                                      cwd=cwd, env=env, capture_output=True, text=True, check=True, timeout=30)
            streams = json.loads(response.stdout)['streams']
            if len(streams) != 1 or streams[0]['codec_type'] != 'audio' or streams[0]['codec_name'] != expected_codec:
                raise AssertionError('Installed toolkit selected an incorrect audio route')
            if (streams[0]['sample_rate'], streams[0]['channels']) != ('48000', 2):
                raise AssertionError('Installed preparation changed sample rate/channel count')
        if hashes != [hashlib.sha256(path.read_bytes()).hexdigest() for path in sources]:
            raise AssertionError('Installed video preparation modified a source file')
        if list(storage.iterdir()):
            raise AssertionError('Installed command left owned PHAR/process/media temporary files')
        print('Installed native toolkit lookup/audio-only upload accepted: ' + str(binary))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--binary', type=Path, required=True)
    parser.add_argument('--fixtures', type=Path,
                        default=Path(__file__).resolve().parents[2] / 'tests/Fixtures/Media')
    arguments = parser.parse_args()
    accept(arguments.binary, arguments.fixtures)
