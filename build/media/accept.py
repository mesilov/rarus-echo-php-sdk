#!/usr/bin/env python3
"""Require the pinned capabilities, offline conversion, and native system-only linkage."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tempfile

from toolkit import CONFIG, HERE, ROOT, run, verify_payload, verify_source_package, validate_codecs, configured_components


def command(binary, args, cwd, env):
    return run([binary, *args], cwd, env, timeout=60)


def accept(root, target, linkage=False):
    root = Path(root).resolve()
    info = verify_payload(root, target)
    verify_source_package(root)
    suffix = '.exe' if target == 'windows-amd64' else ''
    ffmpeg, ffprobe = [root / 'libexec' / (name + suffix) for name in ['ffmpeg', 'ffprobe']]
    if linkage:
        for binary in [ffmpeg, ffprobe]:
            print(run([sys.executable, ROOT / 'build/tests/linkage-check.py', binary]))
    env = {k: v for k, v in os.environ.items() if not k.startswith('RARUS_ECHO_')}
    env['PATH'] = ''
    with tempfile.TemporaryDirectory(prefix='echo media проверка ') as temp:
        cwd = Path(temp)
        for binary in [ffmpeg, ffprobe]:
            version = command(binary, ['-version'], cwd, env)
            if not version.startswith(binary.stem + ' version ' + CONFIG['ffmpeg_version'] + ' '):
                raise ValueError('Unexpected FFmpeg release')
            config_line = next(line for line in version.splitlines() if line.startswith('configuration:'))
            effective = shlex.split(config_line.removeprefix('configuration:').strip())
            if effective != info['configure_args']:
                raise ValueError('Manifest configure flags differ from the shipped binaries')
            for required in ['--disable-autodetect', '--disable-network', '--disable-gpl', '--disable-nonfree']:
                if required not in config_line:
                    raise ValueError('Unsafe media capability configuration')
            if any(flag in config_line for flag in ['--enable-gpl', '--enable-nonfree', '--enable-lib', '--enable-network']):
                raise ValueError('External/GPL/nonfree/network media components enabled')
        protocols = command(ffmpeg, ['-hide_banner', '-protocols'], cwd, env)
        names = {line.strip() for line in protocols.splitlines()
                 if re.fullmatch(r'\s+[a-z][a-z0-9_]*\s*', line)}
        if names != {'file', 'pipe'}:
            raise ValueError('Unexpected network or input protocol: ' + str(names))
        for option in ['-decoders', '-encoders']:
            codecs = command(ffmpeg, ['-hide_banner', option], cwd, env)
            validate_codecs(codecs, 'decoder' if option == '-decoders' else 'encoder')
        aliases = {'mov,mp4,m4a,3gp,3g2,mj2': 'mov', 'matroska,webm': 'matroska',
                   'mpeg': 'mpegps', 's16le': 'pcm_s16le'}
        demuxers = command(ffmpeg, ['-hide_banner', '-demuxers'], cwd, env)
        names = {aliases.get(name, name) for name in re.findall(r'^ D\s+(\S+)', demuxers, re.MULTILINE)}
        if names != configured_components('demuxer'):
            raise ValueError('Unexpected or missing demuxer capabilities')
        muxers = command(ffmpeg, ['-hide_banner', '-muxers'], cwd, env)
        names = set(re.findall(r'^  E\s+(\S+)', muxers, re.MULTILINE))
        if names != configured_components('muxer') | {'mov'}:
            raise ValueError('Unexpected or missing muxer capabilities')
        devices = command(ffmpeg, ['-hide_banner', '-devices'], cwd, env)
        if re.search(r'^ [ D][ E]\s+(?![=])(\S+)', devices, re.MULTILINE):
            raise ValueError('Unexpected capture/playback device')
        filters = command(ffmpeg, ['-hide_banner', '-filters'], cwd, env)
        for name in ['asetpts', 'adelay', 'atrim', 'aresample', 'aformat', 'anull']:
            if not re.search(r'\s' + name + r'\s', filters):
                raise ValueError('Required timeline filter missing: ' + name)
        pcm = cwd / 'звук $ literal.pcm'
        pcm.write_bytes(b'\x01\x00' * 4800 * 2)
        output, copied = cwd / 'prepared.flac', cwd / 'copied.flac'
        command(ffmpeg, ['-nostdin', '-v', 'error', '-f', 's16le', '-ar', '48000', '-ac', '2',
            '-i', str(pcm), '-af', 'asetpts=PTS-STARTPTS,adelay=100:all=1,atrim=duration=0.2,aresample=48000,aformat=sample_fmts=s16,anull',
            '-c:a', 'flac', str(output)], cwd, env)
        probe = json.loads(command(ffprobe, ['-v', 'error', '-show_streams', '-of', 'json', str(output)], cwd, env))
        stream = probe['streams'][0]
        if (stream['codec_name'], stream['sample_rate'], stream['channels']) != ('flac', '48000', 2):
            raise ValueError('Audio rate/channel/codec preservation failed')
        command(ffmpeg, ['-nostdin', '-v', 'error', '-i', str(output), '-map', '0:0', '-vn',
                         '-c:a', 'copy', str(copied)], cwd, env)
        packets = []
        for path in [output, copied]:
            data = json.loads(command(ffprobe, ['-v', 'error', '-show_packets', '-show_data_hash', 'sha256',
                                                '-of', 'json', path], cwd, env))
            packets.append([(p['size'], p['data_hash']) for p in data['packets']])
        if packets[0] != packets[1]:
            raise ValueError('Stream copy changed encoded audio packets')
        decoded = cwd / 'decoded.wav'
        command(ffmpeg, ['-nostdin', '-v', 'error', '-i', output, '-c:a', 'pcm_s16le', decoded], cwd, env)
        import wave
        with wave.open(str(decoded)) as wav:
            signal = wav.readframes(wav.getnframes())
            if wav.getframerate() != 48000 or wav.getnchannels() != 2 or signal[:4800 * 4] != bytes(4800 * 4):
                raise ValueError('Leading silence/timeline normalization failed')
        corpus(ffmpeg, ffprobe, cwd, env)
        print('Accepted pinned offline media toolkit: ' + target)
    return info


def corpus(ffmpeg, ffprobe, cwd, env):
    fixtures = ROOT / 'tests/Fixtures/Media'
    manifest = json.loads((fixtures / 'manifest.json').read_text(encoding='utf-8'))
    for name, fixture in manifest['fixtures'].items():
        source = fixtures / name
        if hashlib.sha256(source.read_bytes()).hexdigest() != fixture['sha256']:
            raise ValueError('Fixture checksum differs: ' + name)
        if name == 'corrupt.mp4':
            result = subprocess.run([str(ffprobe), '-v', 'error', '-show_streams', '-of', 'json', str(source)],
                                    env=env, cwd=cwd, capture_output=True, timeout=30)
            if result.returncode == 0:
                raise ValueError('Corrupt fixture was accepted')
            continue
        actual = json.loads(command(ffprobe, ['-v', 'error', '-show_streams', '-of', 'json', source], cwd, env))
        expected = [(s['index'], s['codec_type']) for s in fixture['streams']]
        if [(s['index'], s['codec_type']) for s in actual['streams']] != expected:
            raise ValueError('Fixture stream classification differs: ' + name)
        audio = [s for s in actual['streams'] if s['codec_type'] == 'audio']
        if not audio:
            continue
        defaults = [s for s in audio if s.get('disposition', {}).get('default') == 1]
        selected = defaults[0] if len(defaults) == 1 else min(audio, key=lambda s: s['index'])
        if name == 'two-tracks.mkv' and selected['index'] != 2:
            raise ValueError('Default track fixture is not track two')
        copies = {'aac': '.m4a', 'opus': '.ogg', 'flac': '.flac', 'pcm_s16le': '.wav', 'mp3': '.mp3'}
        codec = selected['codec_name']
        args = ['-nostdin', '-v', 'error', '-i', source, '-map', '0:' + str(selected['index']), '-vn']
        copy = codec in copies and name != 'delayed.mp4'
        output = cwd / ('copy-' + name + (copies[codec] if copy else '.flac'))
        if copy:
            args += ['-c:a', 'copy']
        else:
            args += ['-c:a', 'flac']
            if name == 'delayed.mp4':
                delay = round(float(selected['start_time']) * 1000)
                args += ['-af', 'asetpts=PTS-STARTPTS,adelay=' + str(delay) + ':all=1']
        command(ffmpeg, [*args, output], cwd, env)
        checked = json.loads(command(ffprobe, ['-v', 'error', '-show_streams', '-show_packets',
            '-show_data_hash', 'sha256', '-of', 'json', output], cwd, env))
        streams = checked['streams']
        if len(streams) != 1 or streams[0]['codec_type'] != 'audio' or any(
                streams[0][key] != selected[key] for key in ['sample_rate', 'channels']):
            raise ValueError('Audio channel/rate preparation changed: ' + name)
        if copy:
            packet_hash = hashlib.sha256(('\n'.join(p['data_hash'] for p in checked['packets']) + '\n').encode()).hexdigest()
            if packet_hash != fixture['audio_packet_sha256'][str(selected['index'])]:
                raise ValueError('Copy changed encoded packets: ' + name)
        if name == 'delayed.mp4':
            decoded = cwd / 'delayed-decoded.wav'
            command(ffmpeg, ['-nostdin', '-v', 'error', '-i', output, '-c:a', 'pcm_s16le', decoded], cwd, env)
            import wave
            import array
            with wave.open(str(decoded)) as wav:
                values = array.array('h', wav.readframes(wav.getnframes()))
                if sys.byteorder != 'little':
                    values.byteswap()
                first = next((i // wav.getnchannels() / wav.getframerate() for i, v in enumerate(values)
                              if abs(v) > 200), None)
                if first is None or abs(first - 2.5) > 0.05:
                    raise ValueError('Delayed signal alignment exceeds 50ms: ' + str(first))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('payload', type=Path)
    parser.add_argument('--target', choices=CONFIG['targets'], required=True)
    parser.add_argument('--linkage', action='store_true')
    args = parser.parse_args()
    accept(args.payload, args.target, args.linkage)
