#!/usr/bin/env python3
"""Regenerate the project's original CC0 media acceptance fixtures.

Requires a full development FFmpeg, including lavfi, MPEG-4, AAC, libopus and
libmp3lame encoders. The shipped compact toolkit never generates these fixtures.
"""
import argparse
import hashlib
import json
import math
from pathlib import Path
import struct
import subprocess
import tempfile
import wave


ROOT = Path(__file__).resolve().parent


def execute(command):
    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            text=True, check=False)
    if result.returncode:
        raise RuntimeError(result.stderr)
    return result.stdout


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--ffmpeg', default='ffmpeg')
    parser.add_argument('--ffprobe', default='ffprobe')
    args = parser.parse_args()

    def ff(output, *arguments):
        execute([args.ffmpeg, '-nostdin', '-v', 'error', '-y', *arguments, str(ROOT / output)])

    signal = ROOT / 'stereo.wav'
    with wave.open(str(signal), 'wb') as stream:
        stream.setnchannels(2)
        stream.setsampwidth(2)
        stream.setframerate(48000)
        samples = bytearray()
        for index in range(96000):
            t = index / 48000
            for frequency in (440, 880):
                value = 0 if t < 0.5 else round(8192 * math.sin(2 * math.pi * frequency * t))
                samples.extend(struct.pack('<h', value))
        stream.writeframes(samples)

    ff('video-aac.mp4', '-f', 'lavfi', '-i', 'color=c=black:s=96x64:r=10',
       '-i', str(signal), '-t', '2', '-c:v', 'mpeg4', '-c:a', 'aac', '-b:a', '64k')
    ff('video-opus.webm', '-f', 'lavfi', '-i', 'color=c=black:s=96x64:r=10',
       '-i', str(signal), '-t', '2', '-c:v', 'libvpx-vp9', '-deadline', 'realtime',
       '-cpu-used', '8', '-c:a', 'libopus', '-b:a', '64k')
    ff('video-ac3.mkv', '-i', str(ROOT / 'video-aac.mp4'), '-c:v', 'copy', '-c:a', 'ac3')
    ff('video-flac.mkv', '-i', str(ROOT / 'video-aac.mp4'), '-c:v', 'copy', '-c:a', 'flac')
    ff('two-tracks.mkv', '-i', str(ROOT / 'video-aac.mp4'), '-i', str(signal),
       '-map', '0:v', '-map', '0:a', '-map', '1:a', '-c:v', 'copy', '-c:a', 'aac',
       '-b:a', '64k', '-disposition:a:0', '0', '-disposition:a:1', 'default',
       '-metadata:s:a:0', 'language=eng', '-metadata:s:a:1', 'language=rus')
    ff('delayed.mp4', '-f', 'lavfi', '-i', 'color=c=black:s=96x64:r=10',
       '-itsoffset', '2', '-i', str(signal), '-t', '4', '-c:v', 'mpeg4', '-c:a', 'aac', '-b:a', '64k')
    ff('audio-only.m4a', '-i', str(ROOT / 'video-aac.mp4'), '-map', '0:a:0', '-c:a', 'copy')
    ff('audio-only.webm', '-i', str(ROOT / 'video-opus.webm'), '-map', '0:a:0', '-c:a', 'copy')
    ff('no-audio.mp4', '-i', str(ROOT / 'video-aac.mp4'), '-map', '0:v', '-c:v', 'copy')
    with tempfile.TemporaryDirectory(prefix='echo-fixture-') as temp:
        cover = Path(temp) / 'cover.png'
        execute([args.ffmpeg, '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i',
                 'color=c=blue:s=32x32', '-frames:v', '1', str(cover)])
        ff('artwork.mp3', '-i', str(signal), '-i', str(cover), '-map', '0:a', '-map', '1:v',
           '-c:a', 'libmp3lame', '-b:a', '64k', '-c:v', 'copy', '-id3v2_version', '3',
           '-disposition:v', 'attached_pic')
    (ROOT / 'corrupt.mp4').write_bytes(b'RARUS Echo invalid-media acceptance fixture\n')

    manifest = {'schema_version': 1,
                'generated_with': execute([args.ffmpeg, '-version']).splitlines()[0],
                'fixtures': {}}
    for path in sorted(ROOT.iterdir()):
        if path.suffix not in {'.wav', '.mp4', '.webm', '.mkv', '.m4a', '.mp3'}:
            continue
        item = {'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'bytes': path.stat().st_size}
        if path.name != 'corrupt.mp4':
            probe = json.loads(execute([args.ffprobe, '-v', 'error', '-show_entries',
                'format=format_name,start_time,duration:stream=index,codec_type,codec_name,sample_rate,channels,start_time,duration:stream_disposition=default,attached_pic',
                '-of', 'json', str(path)]))
            item.update(probe)
            item['audio_packet_sha256'] = {}
            for stream in probe['streams']:
                if stream['codec_type'] != 'audio':
                    continue
                packets = json.loads(execute([args.ffprobe, '-v', 'error', '-select_streams',
                    str(stream['index']), '-show_packets', '-show_data_hash', 'sha256',
                    '-show_entries', 'packet=data_hash', '-of', 'json', str(path)]))
                hashes = '\n'.join(p['data_hash'] for p in packets['packets']) + '\n'
                item['audio_packet_sha256'][str(stream['index'])] = hashlib.sha256(hashes.encode()).hexdigest()
        manifest['fixtures'][path.name] = item
    (ROOT / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('Generated', len(manifest['fixtures']), 'fixtures,',
          sum(x['bytes'] for x in manifest['fixtures'].values()), 'bytes')


if __name__ == '__main__':
    main()
