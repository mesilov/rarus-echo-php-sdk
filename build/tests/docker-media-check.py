#!/usr/bin/env python3
"""Accept a locally loaded Docker candidate, before any registry publication."""
import argparse
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import http.server
import threading
import hashlib
import email.parser
import email.policy

ROOT = Path(__file__).resolve().parents[2]


def docker(*args, **kwargs):
    result = subprocess.run(['docker', *map(str, args)], check=True, capture_output=True, **kwargs)
    return result.stdout


class UploadHandler(http.server.BaseHTTPRequestHandler):
    received = []

    def log_message(self, *args):
        pass

    def do_POST(self):
        body = self.rfile.read(int(self.headers['Content-Length']))
        self.received.append((dict(self.headers), body))
        data = b'{"results":[{"file_id":"33333333-3333-4333-8333-333333333333"}]}'
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)


def submit_check(image, platform, fixtures):
    server = http.server.ThreadingHTTPServer(('0.0.0.0', 0), UploadHandler)
    server.daemon_threads = True
    worker = threading.Thread(target=server.serve_forever, daemon=True)
    worker.start()
    original = hashlib.sha256((fixtures / 'video-aac.mp4').read_bytes()).hexdigest()
    try:
        output = docker('run', '--rm', '--platform', platform, '--read-only', '--workdir', '/tmp',
            '--tmpfs', '/tmp:rw,noexec,nosuid,size=128m',
            '--add-host', 'host.docker.internal:host-gateway',
            '--mount', 'type=bind,src=' + str(fixtures) + ',dst=/inputs,readonly',
            '-e', 'RARUS_ECHO_API_KEY=11111111-1111-4111-8111-111111111111',
            '-e', 'RARUS_ECHO_USER_ID=22222222-2222-4222-8222-222222222222',
            '-e', 'RARUS_ECHO_BASE_URL=http://host.docker.internal:' + str(server.server_port),
            image, 'submit', '/inputs/video-aac.mp4', '--json')
        if json.loads(output)['file_ids'] != ['33333333-3333-4333-8333-333333333333'] or len(UploadHandler.received) != 1:
            raise ValueError('Docker video submit did not make exactly one controlled upload')
        headers, body = UploadHandler.received[0]
        message = email.parser.BytesParser(policy=email.policy.default).parsebytes(
            ('Content-Type: ' + headers['Content-Type'] + '\r\nMIME-Version: 1.0\r\n\r\n').encode() + body)
        files = [part for part in message.iter_parts() if part.get_filename()]
        if len(files) != 1 or (fixtures / 'video-aac.mp4').read_bytes() in body:
            raise ValueError('Docker uploaded original video or unexpected multipart files')
        with tempfile.TemporaryDirectory(prefix='echo-upload-audio-') as temp:
            received = Path(temp) / 'received-audio'
            received.write_bytes(files[0].get_payload(decode=True))
            output = docker('run', '--rm', '--network', 'none', '--platform', platform,
                '--mount', 'type=bind,src=' + temp + ',dst=/upload,readonly',
                '--entrypoint', '/opt/rarus-echo/media/libexec/ffprobe', image,
                '-v', 'error', '-show_streams', '-of', 'json', '/upload/received-audio')
            streams = json.loads(output)['streams']
            if len(streams) != 1 or (streams[0]['codec_type'], streams[0]['sample_rate'], streams[0]['channels']) != ('audio', '48000', 2):
                raise ValueError('Docker prepared upload changed channel/rate or retained video')
        if hashlib.sha256((fixtures / 'video-aac.mp4').read_bytes()).hexdigest() != original:
            raise ValueError('Read-only source video changed')
    finally:
        server.shutdown()
        server.server_close()
        worker.join(timeout=5)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('image')
    parser.add_argument('--platform', choices=['linux/amd64', 'linux/arm64'], required=True)
    parser.add_argument('--baseline', required=True, help='Named, pinned no-media image baseline')
    args = parser.parse_args()
    target = args.platform.replace('/', '-')
    with tempfile.TemporaryDirectory(prefix='echo-docker-accept-') as temp:
        root = Path(temp)
        fixtures = ROOT / 'tests/Fixtures/Media'
        media = root / 'media'
        container = docker('create', '--platform', args.platform, args.image).decode().strip()
        try:
            docker('cp', container + ':/opt/rarus-echo/media', media)
        finally:
            docker('rm', container)
        sys.path.insert(0, str(ROOT / 'build'))
        from media import toolkit
        toolkit.verify_payload(media, target)
        mounts = ['--network', 'none', '--read-only', '--tmpfs', '/tmp:rw,noexec,nosuid,size=64m',
                  '--mount', 'type=bind,src=' + str(fixtures) + ',dst=/inputs,readonly']
        prefix = ['run', '--rm', '--platform', args.platform, *mounts]
        help_text = docker(*prefix, args.image, 'submit', '--help')
        if b'--media-timeout' not in help_text:
            raise ValueError('Candidate does not expose media submit options')
        # Run the full accepted toolkit against the read-only corpus using the candidate image's own tools.
        script = '''set -eu
p=/opt/rarus-echo/media/libexec
"$p/ffprobe" -v error -show_streams -of json /inputs/video-aac.mp4 > /tmp/probe.json
"$p/ffmpeg" -nostdin -v error -i /inputs/video-aac.mp4 -map 0:1 -vn -c:a copy /tmp/copied.m4a
"$p/ffmpeg" -nostdin -v error -i /inputs/video-ac3.mkv -map 0:1 -vn -c:a flac /tmp/prepared.flac
"$p/ffprobe" -v error -show_streams -of json /tmp/prepared.flac
for tool in cc gcc make python3 ffplay; do if command -v "$tool" >/dev/null; then exit 1; fi; done
[ ! -e /opt/rarus-echo/media/source ]
'''
        output = docker(*prefix, '--entrypoint', '/bin/sh', args.image, '-c', script)
        streams = json.loads(output)['streams']
        if len(streams) != 1 or (streams[0]['codec_name'], streams[0]['channels'], streams[0]['sample_rate']) != ('flac', 2, '48000'):
            raise ValueError('Docker audio conversion contract failed')
        submit_check(args.image, args.platform, fixtures)
        current = json.loads(docker('image', 'inspect', args.image))[0]
        baseline = json.loads(docker('image', 'inspect', args.baseline))[0]
        # Save to disk, then decode/count each layer as a bounded stream. Engine Size may
        # represent compressed blobs with Docker Desktop/containerd, so it is kept separate.
        from media.image_size import saved_image_metrics
        candidate_saved, baseline_saved = root / 'candidate-image.tar', root / 'baseline-image.tar'
        docker('image', 'save', args.image, '--output', candidate_saved)
        candidate_size = saved_image_metrics(candidate_saved)
        candidate_saved.unlink()
        docker('image', 'save', args.baseline, '--output', baseline_saved)
        baseline_size = saved_image_metrics(baseline_saved)
        baseline_saved.unlink()
        if not candidate_size['media_layers']:
            raise ValueError('Complete media payload layer was not measured')
        compressed = candidate_size['media_layer_gzip_bytes']
        if compressed > toolkit.CONFIG['maximum_compressed_increment']:
            raise ValueError('Compressed complete Docker media payload exceeds 25 MiB')
        report = {'platform': args.platform, 'baseline': args.baseline, 'baseline_image_id': baseline['Id'],
                  'candidate_image_id': current['Id'], 'engine_reported_baseline_size': baseline['Size'],
                  'engine_reported_complete_size': current['Size'],
                  'uncompressed_measurement': 'sum of decoded saved layer tar stream bytes, including tar headers/padding',
                  'baseline_uncompressed_bytes': baseline_size['uncompressed_layer_tar_bytes'],
                  'complete_uncompressed_bytes': candidate_size['uncompressed_layer_tar_bytes'],
                  'image_uncompressed_increment_bytes': candidate_size['uncompressed_layer_tar_bytes'] - baseline_size['uncompressed_layer_tar_bytes'],
                  'compressed_measurement': 'saved gzip blobs; canonical level-9 gzip for raw saved layers',
                  'baseline_layer_gzip_bytes': baseline_size['layer_gzip_bytes'],
                  'complete_layer_gzip_bytes': candidate_size['layer_gzip_bytes'],
                  'image_layer_gzip_increment_bytes': candidate_size['layer_gzip_bytes'] - baseline_size['layer_gzip_bytes'],
                  'complete_media_layer_gzip_bytes': compressed}
        destination = ROOT / 'dist' / ('docker-media-size-' + target + '.json')
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(json.dumps(report, indent=2) + '\n')
        print(json.dumps(report, indent=2))
        print('Accepted Docker media candidate before publication: ' + target)


if __name__ == '__main__':
    main()
