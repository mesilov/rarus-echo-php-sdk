#!/usr/bin/env python3
"""Offline installer contract; optional real native package through controlled curl.

Adapted from bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00 (BSD-3-Clause).
See build/BITRIX24-CLI-LICENSE.txt.
"""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess
import sys
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
BASE = 'https://github.com/mesilov/rarus-echo-php-sdk/releases'
parser = argparse.ArgumentParser()
parser.add_argument('--assets', type=Path)
parser.add_argument('--version')
args = parser.parse_args()
NATIVE_TARGET = ('macos-arm64' if platform.system() == 'Darwin' else
                 'linux-arm64' if platform.machine() in ('arm64', 'aarch64') else 'linux-amd64')
SHA = 'a' * 40
MEDIA_SHA = 'b' * 64
PROFILE_SHA = 'c' * 64
LEGACY_VERSION = '0.5.0-rc-1'
LEGACY_SHA = 'e359975527bd483707dc39d23599545a77c1620d'


def identity(version):
    return 'RARUS Echo CLI ' + version + ' (source ' + SHA + ')'


class InstallerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='rarus-echo installer ')
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name).resolve()
        self.home = self.root / "user's домашний каталог"
        self.home.mkdir()
        self.tools = self.root / 'tools'
        self.tools.mkdir()
        self.assets = self.root / 'assets'
        self.assets.mkdir()
        self.log = self.root / 'requests'
        self.env = {'HOME': str(self.home), 'PATH': str(self.tools) + ':/usr/bin:/bin',
                    'TMPDIR': str(self.root), 'FIXTURE_ASSETS': str(self.assets),
                    'FIXTURE_LOG': str(self.log), 'FIXTURE_TAG': '0.2.0',
                    'FIXTURE_OS': 'Linux', 'FIXTURE_ARCH': 'x86_64',
                    'ZDOTDIR': str(self.home / 'zsh settings'), 'LC_ALL': 'C'}
        self.script('uname', '#!/bin/sh\ncase "$1" in -s) echo "$FIXTURE_OS";; -m) echo "$FIXTURE_ARCH";; esac\n')
        self.script('curl', '''#!/bin/sh
out=
url=
while [ "$#" -gt 0 ]; do
    case "$1" in
      --output) out=$2; shift 2;;
      --write-out|--proto|--proto-redir|--connect-timeout|--max-time) shift 2;;
      https:*) url=$1; shift;;
      *) shift;;
    esac
done
printf '%s\\n' "$url" >> "$FIXTURE_LOG"
[ "${FIXTURE_DOWNLOAD_FAIL:-}" != "${url##*/}" ] || exit 22
case "$url" in
 */latest) printf 'https://github.com/mesilov/rarus-echo-php-sdk/releases/tag/%s' "$FIXTURE_TAG";;
 */download/"$FIXTURE_TAG"/*) cp "$FIXTURE_ASSETS/${url##*/}" "$out";;
 *) exit 22;;
esac
''')
        if not shutil.which('sha256sum', path='/usr/bin:/bin'):
            self.script('sha256sum', '#!/bin/sh\nexec /usr/bin/shasum -a 256 "$@"\n')
        self.package()

    def script(self, name, text):
        path = self.tools / name
        path.write_text(text)
        path.chmod(0o755)

    def package(self, version='0.2.0', target='linux-amd64', mode=None, source_sha=None):
        source_sha = source_sha or SHA
        name = 'rarus-echo-' + target + '.tar.gz'
        archive = self.assets / name
        files = {'rarus-echo': '#!/bin/sh\nprintf "' + identity(version).replace(SHA, source_sha) + '\\n"\n',
                 'build-info.json': json.dumps({'version': version, 'source_sha': source_sha, 'target': target,
                     'toolchain_patches': {'build/patches/native.json': 'd' * 64},
                     'media': {'ffmpeg_version': '9.0.2', 'source_sha256': MEDIA_SHA,
                               'profile_sha256': PROFILE_SHA}}),
                 'runtime-sources.json': '{}',
                 'THIRD-PARTY-NOTICES.md': 'notices for ' + version,
                 'RARUS-ECHO-LICENSE.txt': 'own license', 'BITRIX24-CLI-LICENSE.txt': 'upstream', 'YIIPRESS-LICENSE.txt': 'upstream',
                 'runtime-licenses/runtime.txt': 'runtime license'}
        files.update({'libexec/ffmpeg': '''#!/bin/sh
if [ "$1" = -version ]; then echo 'ffmpeg version 9.0.2 fixture'; exit; fi
for last do :; done
printf 'fixture FLAC' > "$last"
''', 'libexec/ffprobe': '''#!/bin/sh
if [ "$1" = -version ]; then echo 'ffprobe version 9.0.2 fixture'; exit; fi
printf 'flac\\n'
''', 'media-licenses/FFmpeg.txt': 'LGPL', 'media-source.tar.xz': 'source fixture'})
        if mode == 'wrong-ffmpeg': files['libexec/ffmpeg'] = '#!/bin/sh\necho "ffmpeg version 8.0 fixture"\n'
        if mode == 'wrong-ffprobe': files['libexec/ffprobe'] = '#!/bin/sh\necho "ffprobe version 8.0 fixture"\n'
        if mode == 'conversion-failure': files['libexec/ffmpeg'] = '#!/bin/sh\n[ "$1" = -version ] || exit 9\necho "ffmpeg version 9.0.2 fixture"\n'
        if mode == 'pcm-sample-check':
            files['libexec/ffmpeg'] = '''#!/bin/sh
if [ "$1" = -version ]; then echo 'ffmpeg version 9.0.2 fixture'; exit; fi
input=
while [ "$#" -gt 0 ]; do
    if [ "$1" = -i ]; then input=$2; fi
    last=$1
    shift
done
[ -n "$input" ] && [ "$(wc -c < "$input")" -eq 2048 ] || exit 9
printf 'fixture FLAC' > "$last"
'''
        media = {'schema_version': 1, 'ffmpeg_version': '9.0.2', 'source_sha256': MEDIA_SHA,
                 'profile_sha256': PROFILE_SHA, 'target': target, 'configure_args': [],
                 'compiler': 'fixture', 'minimum_os': 'fixture',
                 'binaries': {tool: {'path': 'libexec/' + tool,
                     'sha256': hashlib.sha256(files['libexec/' + tool].encode()).hexdigest()}
                     for tool in ['ffmpeg', 'ffprobe']},
                 'source_package': {'path': 'media-source.tar.xz',
                     'sha256': hashlib.sha256(files['media-source.tar.xz'].encode()).hexdigest()}}
        if mode == 'wrong-media-target': media['target'] = 'other-target'
        if mode == 'wrong-media-identity': media['profile_sha256'] = 'd' * 64
        if mode == 'wrong-schema-type': media['schema_version'] = '1'
        if mode == 'wrong-configure-type': media['configure_args'] = '--disable-everything'
        if mode == 'missing-compiler': del media['compiler']
        files['media-build-info.json'] = json.dumps(media)
        media_records = [hashlib.sha256(files[name].encode()).hexdigest() + '  ' + name + '\n'
                         for name in ['libexec/ffmpeg', 'libexec/ffprobe', 'media-build-info.json', 'media-source.tar.xz']]
        files['media-SHA256SUMS'] = ''.join(media_records)
        if mode == 'tampered-media': files['libexec/ffmpeg'] += '# tampered\n'
        if mode == 'duplicate-media-checksum': files['media-SHA256SUMS'] += media_records[0]
        if mode == 'missing-media-checksum': files['media-SHA256SUMS'] = ''.join(media_records[1:])
        if mode == 'extra-media-checksum': files['media-SHA256SUMS'] += '0' * 64 + '  arbitrary\n'
        if mode == 'malformed-media-json': files['media-build-info.json'] = '{not JSON}'
        if mode == 'duplicate-media-key':
            files['media-build-info.json'] = files['media-build-info.json'].replace('"schema_version": 1', '"schema_version": 1, "schema_version": 1')
        if mode in ('malformed-media-json', 'duplicate-media-key'):
            files['media-SHA256SUMS'] = files['media-SHA256SUMS'].replace(media_records[2], hashlib.sha256(files['media-build-info.json'].encode()).hexdigest() + '  media-build-info.json\n')
        if mode == 'missing-media': del files['libexec/ffprobe']
        if mode == 'missing-media-license': del files['media-licenses/FFmpeg.txt']
        if mode == 'missing-license':
            del files['runtime-licenses/runtime.txt']
        if mode == 'empty-license-directory':
            del files['runtime-licenses/runtime.txt']
        if mode == 'dot-alias':
            files['runtime-licenses/./runtime.txt'] = 'overwritten license'
        if mode == 'candidate-failure':
            files['rarus-echo'] = '#!/bin/sh\nexit 9\n'
        if mode == 'staged-failure':
            files['rarus-echo'] = '#!/bin/sh\ncase "$0" in */.rarus-echo.*) exit 9;; esac\nprintf "RARUS Echo CLI 0.2.0 (source aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa)\\n"\n'
        if mode == 'traversal':
            files['../escape'] = 'unsafe'
        if mode == 'absolute':
            files['/tmp/escape'] = 'unsafe'
        if mode == 'unexpected':
            files['.env'] = 'secret'
        if mode == 'invalid-sha':
            files['rarus-echo'] = '#!/bin/sh\nprintf "RARUS Echo CLI 0.2.0 (source nope)\\n"\n'
        if mode == 'empty-license':
            files['RARUS-ECHO-LICENSE.txt'] = ''
        if mode and mode.startswith('legacy'):
            files = {name: data for name, data in files.items()
                     if not name.startswith(('libexec/', 'media-'))}
            metadata = json.loads(files['build-info.json'])
            del metadata['media']
            files['build-info.json'] = json.dumps(metadata)
            if mode == 'legacy-media-entry': files['media-build-info.json'] = '{}'
            if mode == 'legacy-wrong-native': files['rarus-echo'] = '#!/bin/sh\nprintf "' + identity(version) + '\\n"\n'
            if mode == 'legacy-missing-license': del files['runtime-licenses/runtime.txt']
        if mode in ('wrapped-identity', 'legacy-wrapped-identity'):
            files['build-info.json'] = json.dumps({'': json.loads(files['build-info.json'])})
        with tarfile.open(archive, 'w:gz') as tar:
            for filename, value in files.items():
                data = value.encode()
                item = tarfile.TarInfo(filename)
                item.size = len(data)
                item.mode = 0o755 if filename in ('rarus-echo', 'libexec/ffmpeg', 'libexec/ffprobe') else 0o644
                if mode == 'wrong-mode' and filename == 'libexec/ffmpeg': item.mode = 0o644
                if mode == 'special-mode' and filename == 'media-build-info.json': item.mode = 0o4644
                if mode == 'legacy-wrong-mode' and filename == 'rarus-echo': item.mode = 0o644
                tar.addfile(item, io.BytesIO(data))
            if mode in ('symlink', 'hardlink'):
                item = tarfile.TarInfo('runtime-licenses/link')
                item.type = tarfile.SYMTYPE if mode == 'symlink' else tarfile.LNKTYPE
                item.linkname = '/tmp/escape'
                tar.addfile(item)
            if mode in ('duplicate-member', 'case-collision'):
                item = tarfile.TarInfo('rarus-echo' if mode == 'duplicate-member' else 'runtime-licenses/RUNTIME.txt')
                item.size = 4
                tar.addfile(item, io.BytesIO(b'evil'))
            if mode == 'empty-license-directory':
                item = tarfile.TarInfo('runtime-licenses/empty/')
                item.type = tarfile.DIRTYPE
                tar.addfile(item)
        self.checksum(archive)
        return archive

    def checksum(self, archive):
        self.record = hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n'
        (self.assets / 'SHA256SUMS').write_text(self.record)

    def run_installer(self, *options, success=True, interrupted=False):
        result = subprocess.run(['/bin/sh', str(ROOT / 'install.sh'), *options], env=self.env,
                                cwd=self.root, text=True, capture_output=True)
        if success:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        else:
            self.assertNotEqual(result.returncode, 0, result.stdout)
            if not interrupted:
                self.assertIn('rarus-echo installer:', result.stderr)
        self.assertFalse(list(self.root.glob('rarus-echo-install.*')))
        self.assertFalse(list((self.home / '.local/bin').glob('.rarus-echo.*')))
        self.assertFalse(list((self.home / '.local/share/rarus-echo').glob('.package.*')))
        self.assertFalse(list((self.home / '.local/share/rarus-echo').glob('.previous*')))
        self.assertFalse(list((self.home / '.local/bin').glob('.previous.*')))
        self.assertFalse(list((self.home / '.local/bin/.rarus-echo/packages').glob('.stage.*')))
        return result

    def sentinel(self):
        paths = ['.local/bin/rarus-echo', '.config/rarus-echo/config.json',
                 '.local/share/rarus-echo/user-data', '.local/share/rarus-echo/package/old-license']
        for name in paths:
            path = self.home / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('previous ' + name)
        return {name: (self.home / name).read_bytes() for name in paths}

    def unchanged(self, before):
        for name, value in before.items():
            self.assertEqual((self.home / name).read_bytes(), value, name)

    def test_install_repeat_update_and_shells(self):
        before = self.sentinel()
        self.run_installer()
        self.run_installer()
        share = self.home / '.local/share/rarus-echo'
        self.assertTrue((share / 'package/runtime-licenses/runtime.txt').exists())
        package = self.home / ('.local/bin/.rarus-echo/packages/0.2.0-' + SHA)
        self.assertTrue((package / 'libexec/ffmpeg').exists())
        self.assertTrue((package / 'media-source.tar.xz').exists())
        self.assertEqual((self.home / '.local/bin/rarus-echo').stat().st_mode & 0o777, 0o755)
        for name in ['.bashrc', '.profile', 'zsh settings/.zshrc']:
            self.assertEqual((self.home / name).read_text().count('# rarus-echo installer'), 1)
        self.shells('0.2.0', self.home / '.local/bin')
        self.env['FIXTURE_TAG'] = 'v0.3.0-rc.1'
        self.package('v0.3.0-rc.1')
        self.run_installer('--version', 'v0.3.0-rc.1')
        self.shells('v0.3.0-rc.1', self.home / '.local/bin')
        self.assertTrue((package / 'libexec/ffmpeg').exists(), 'update removed the previous immutable toolkit')
        for name in ['.config/rarus-echo/config.json', '.local/share/rarus-echo/user-data']:
            self.assertEqual((self.home / name).read_bytes(), before[name])
        requests = self.log.read_text().splitlines()
        self.assertEqual(requests[-2:], [BASE + '/download/v0.3.0-rc.1/rarus-echo-linux-amd64.tar.gz',
                                        BASE + '/download/v0.3.0-rc.1/SHA256SUMS'])

    def shells(self, version, install):
        env = dict(self.env, PATH='/usr/bin:/bin')
        command = '''cd /; rarus-echo --version --no-ansi; printf '%s\\n' "$PATH"'''
        activation = '. "$HOME/.local/share/rarus-echo/env"; . "$HOME/.local/share/rarus-echo/env"; '
        commands = [['/bin/sh', '-c', activation + command],
                    ['/bin/bash', '--noprofile', '--norc', '-c', '. "$HOME/.profile"; ' + command],
                    ['/bin/bash', '--noprofile', '--rcfile', str(self.home / '.bashrc'), '-ic', command]]
        # Linux login bash resets HOME through /etc/profile. Isolated startup checks
        # above cover the selected login file; native zsh also exercises login itself.
        if platform.system() == 'Darwin':
            commands.append(['/bin/bash', '-lc', command])
        if shutil.which('zsh'):
            commands.extend([[shutil.which('zsh'), '-ic', command],
                             [shutil.which('zsh'), '-lic', command]])
        for cmd in commands:
            result = subprocess.run(cmd, env=env, cwd=self.root, text=True, capture_output=True)
            self.assertEqual(result.returncode, 0, repr(cmd) + result.stderr)
            lines = result.stdout.strip().splitlines()
            self.assertEqual(lines[-2], identity(version))
            self.assertEqual(lines[-1].split(':').count(str(install)), 1)
            self.assertNotIn('.', lines[-1].split(':'))

    def test_custom_and_no_modify_profiles(self):
        for name in ['.bashrc', '.profile', '.bash_profile']:
            (self.home / name).write_text('echo original\n')
        before = {name: (self.home / name).read_bytes() for name in ['.bashrc', '.profile', '.bash_profile']}
        custom = self.root / "custom ' binary dir"
        result = self.run_installer('--install-dir', str(custom), '--no-modify-path')
        self.unchanged(before)
        self.assertFalse((self.home / 'zsh settings/.zshrc').exists())
        self.assertIn(str(custom) + '/rarus-echo', result.stdout)
        result = subprocess.run(['/bin/sh', '-c', '. "$HOME/.local/share/rarus-echo/env"; cd /; rarus-echo --version'],
                                env=self.env, text=True, capture_output=True)
        self.assertEqual(result.stdout.strip(), identity('0.2.0'), result.stderr)
        self.run_installer('--install-dir', str(custom))
        self.assertIn('# rarus-echo installer', (self.home / '.bash_profile').read_text())
        self.assertEqual((self.home / '.profile').read_bytes(), before['.profile'])

    def test_macos_target(self):
        self.env.update(FIXTURE_OS='Darwin', FIXTURE_ARCH='arm64')
        self.package(target='macos-arm64')
        self.run_installer('--no-modify-path')
        self.assertIn('rarus-echo-macos-arm64.tar.gz', self.log.read_text())

    def test_linux_arm64_target(self):
        for arch in ['aarch64', 'arm64']:
            self.env.update(FIXTURE_OS='Linux', FIXTURE_ARCH=arch)
            self.package(target='linux-arm64')
            self.run_installer('--no-modify-path')
        self.assertIn('rarus-echo-linux-arm64.tar.gz', self.log.read_text())

    def test_piped_installation(self):
        result = subprocess.run(['/bin/sh'], input=(ROOT / 'install.sh').read_text(),
                                env=self.env, cwd=self.root, text=True, capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.shells('0.2.0', self.home / '.local/bin')

    def test_truncated_pipe_has_no_side_effects(self):
        script = (ROOT / 'install.sh').read_text().rsplit('\nmain "$@"', 1)[0]
        result = subprocess.run(['/bin/sh'], input=script, env=self.env,
                                cwd=self.root, text=True, capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse(self.log.exists())
        self.assertFalse((self.home / '.local').exists())

    def test_commit_errors_roll_back_installer_owned_files(self):
        real_mv = shutil.which('mv')
        for entry in ['package', 'rarus-echo', 'env']:
            with self.subTest(entry=entry):
                before = self.sentinel()
                activation = self.home / '.local/share/rarus-echo/env'
                activation.write_text('previous activation')
                before['.local/share/rarus-echo/env'] = activation.read_bytes()
                self.script('mv', '''#!/bin/sh
src=$1
[ "$src" != -f ] || src=$2
case "$src" in */.package.*|*/.rarus-echo.*|*/.env.*)
    for last do :; done
    [ "${last##*/}" != "''' + entry + '''" ] || exit 9;;
esac
exec "''' + real_mv + '''" "$@"
''')
                self.run_installer('--no-modify-path', success=False)
                self.unchanged(before)

    def test_term_after_each_successful_activation_move_restores_complete_install(self):
        self.sentinel()
        real_mv = shutil.which('mv')
        for stage in ['notices-backup', 'immutable', 'notices', 'binary', 'env']:
            with self.subTest(stage=stage):
                install = self.root / ('signal ' + stage)
                self.script('mv', '#!/bin/sh\nexec "' + real_mv + '" "$@"\n')
                self.env['FIXTURE_TAG'] = '0.2.0'
                self.package('0.2.0')
                self.run_installer('--no-modify-path', '--install-dir', str(install))
                (self.home / '.local/share/rarus-echo/env').write_text('previous shell activation\n')
                before = {name: (self.home / name).read_bytes() for name in [
                    '.local/share/rarus-echo/env', '.local/share/rarus-echo/package/THIRD-PARTY-NOTICES.md',
                    '.config/rarus-echo/config.json', '.local/share/rarus-echo/user-data']}
                previous_binary = (install / 'rarus-echo').read_bytes()
                previous_package = install / ('.rarus-echo/packages/0.2.0-' + SHA)
                before_tools = {name: (previous_package / name).read_bytes()
                                for name in ['rarus-echo', 'libexec/ffmpeg', 'libexec/ffprobe', 'media-build-info.json']}
                self.env['FIXTURE_TAG'] = '0.3.0'
                self.package('0.3.0')
                self.env['FIXTURE_SIGNAL_STAGE'] = stage
                self.script('mv', '''#!/bin/sh
src=$1
[ "$src" != -f ] || src=$2
"''' + real_mv + '''" "$@" || exit $?
case "$FIXTURE_SIGNAL_STAGE:$src" in
    notices-backup:*/share/rarus-echo/package|immutable:*/packages/.stage.*|notices:*/.package.*|binary:*/.rarus-echo.*|env:*/.env.*)
        kill -TERM "$PPID";;
esac
case "$src" in */.previous.*|*/.previous-env.*) kill -TERM "$PPID";; esac
''')
                result = self.run_installer('--no-modify-path', '--install-dir', str(install), success=False, interrupted=True)
                self.assertEqual(result.returncode, 143, result.stderr)
                self.unchanged(before)
                self.assertEqual((install / 'rarus-echo').read_bytes(), previous_binary)
                for name, content in before_tools.items():
                    self.assertEqual((previous_package / name).read_bytes(), content)
                self.assertFalse((install / ('.rarus-echo/packages/0.3.0-' + SHA)).exists())
                self.assertFalse(list(install.glob('.previous.*')))
                self.assertFalse(list((install / '.rarus-echo/packages').glob('.stage.*')))
                output = subprocess.check_output([str(install / 'rarus-echo'), '--version'], text=True)
                self.assertEqual(output.strip(), identity('0.2.0'))

    def test_symlink_destinations_are_preserved(self):
        for name in ['.local/bin/rarus-echo', '.local/share/rarus-echo/env']:
            with self.subTest(name=name):
                destination = self.home / name
                destination.parent.mkdir(parents=True, exist_ok=True)
                target = self.root / 'manual install'
                target.write_text('previous executable or activation')
                destination.symlink_to(target)
                self.run_installer('--no-modify-path', success=False)
                self.assertTrue(destination.is_symlink())
                self.assertEqual(target.read_text(), 'previous executable or activation')
                destination.unlink()

    def test_failed_notices_backup_cleans_all_stages(self):
        before = self.sentinel()
        real_mv = shutil.which('mv')
        self.script('mv', '''#!/bin/sh
[ "$1" != "$HOME/.local/share/rarus-echo/package" ] || exit 9
exec "''' + real_mv + '''" "$@"
''')
        self.run_installer('--no-modify-path', success=False)
        self.unchanged(before)

    def test_failures_preserve_previous_install(self):
        for scenario in ['download', 'latest', 'checksum-download', 'missing', 'malformed', 'duplicate',
                         'mismatch', 'invalid-archive', 'version', 'candidate-failure',
                         'missing-license', 'empty-license', 'empty-license-directory', 'dot-alias',
                         'traversal', 'absolute', 'symlink', 'hardlink',
                         'unexpected', 'duplicate-member', 'case-collision', 'invalid-sha',
                         'unsupported', 'bad-tag', 'relative-dir', 'staged-failure', 'unpack']:
            with self.subTest(scenario=scenario):
                before = self.sentinel()
                self.env.pop('FIXTURE_DOWNLOAD_FAIL', None)
                self.env.update(FIXTURE_ARCH='x86_64', FIXTURE_TAG='0.2.0')
                archive = self.package(mode=scenario)
                options = []
                if scenario == 'download': self.env['FIXTURE_DOWNLOAD_FAIL'] = archive.name
                if scenario == 'latest': self.env['FIXTURE_DOWNLOAD_FAIL'] = 'latest'
                if scenario == 'checksum-download': self.env['FIXTURE_DOWNLOAD_FAIL'] = 'SHA256SUMS'
                if scenario == 'missing': (self.assets / 'SHA256SUMS').write_text('')
                if scenario == 'malformed': (self.assets / 'SHA256SUMS').write_text('nohash  ' + archive.name + '\n')
                if scenario == 'duplicate': (self.assets / 'SHA256SUMS').write_text(self.record * 2)
                if scenario == 'mismatch': (self.assets / 'SHA256SUMS').write_text('0' * 64 + '  ' + archive.name + '\n')
                if scenario == 'invalid-archive': archive.write_text('invalid'); self.checksum(archive)
                if scenario == 'version': self.package(version='0.0.0')
                if scenario == 'unsupported': self.env['FIXTURE_ARCH'] = 'riscv64'
                if scenario == 'bad-tag': options = ['--version', '../bad']
                if scenario == 'relative-dir': options = ['--install-dir', '.']
                if scenario == 'unpack':
                    real_tar = shutil.which('tar')
                    self.script('tar', '#!/bin/sh\n[ "$1" != -xzf ] || exit 9\nexec ' + real_tar + ' "$@"\n')
                self.run_installer(*options, success=False)
                self.unchanged(before)

    def test_invalid_media_bundle_preserves_previous_install(self):
        for scenario in ['wrong-ffmpeg', 'wrong-ffprobe', 'conversion-failure', 'wrong-media-target',
                         'wrong-media-identity', 'tampered-media', 'duplicate-media-checksum',
                         'missing-media-checksum', 'extra-media-checksum', 'malformed-media-json',
                         'duplicate-media-key', 'missing-media', 'missing-media-license', 'wrong-mode', 'special-mode',
                         'wrong-schema-type', 'wrong-configure-type', 'missing-compiler']:
            with self.subTest(scenario=scenario):
                before = self.sentinel()
                self.package(mode=scenario)
                self.run_installer('--no-modify-path', '--install-dir', str(self.root / scenario), success=False)
                self.unchanged(before)

    def test_existing_immutable_package_is_verified_and_never_overwritten(self):
        self.run_installer('--no-modify-path')
        package = self.home / ('.local/bin/.rarus-echo/packages/0.2.0-' + SHA)
        media = package / 'media-build-info.json'
        before = media.read_bytes()
        self.run_installer('--no-modify-path')
        self.assertEqual(media.read_bytes(), before)
        media.write_text('conflicting manifest')
        self.run_installer('--no-modify-path', success=False)
        self.assertEqual(media.read_text(), 'conflicting manifest')

    def test_media_acceptance_uses_1024_pcm_frames_for_flac(self):
        self.package(mode='pcm-sample-check')
        self.run_installer('--no-modify-path')

    def test_frozen_legacy_release_installs_repeats_and_keeps_native_identity(self):
        self.env['FIXTURE_TAG'] = LEGACY_VERSION
        self.package(LEGACY_VERSION, mode='legacy', source_sha=LEGACY_SHA)
        install = self.root / 'legacy каталог'
        for _ in range(2): self.run_installer('--version', LEGACY_VERSION, '--install-dir', str(install), '--no-modify-path')
        output = subprocess.check_output([str(install / 'rarus-echo'), '--version'], text=True)
        self.assertEqual(output.strip(), 'RARUS Echo CLI ' + LEGACY_VERSION + ' (source ' + LEGACY_SHA + ')')
        package = install / ('.rarus-echo/packages/' + LEGACY_VERSION + '-' + LEGACY_SHA)
        self.assertTrue((package / 'runtime-licenses/runtime.txt').is_file())
        self.assertFalse((package / 'libexec').exists())

    def test_frozen_legacy_exception_never_accepts_unknown_or_partial_media_payloads(self):
        for scenario in ['wrong-source', 'unknown-release', 'legacy-wrong-native', 'legacy-media-entry',
                         'legacy-missing-license', 'legacy-wrong-mode']:
            with self.subTest(scenario=scenario):
                before = self.sentinel()
                version = '0.5.0-rc-2' if scenario == 'unknown-release' else LEGACY_VERSION
                self.env['FIXTURE_TAG'] = version
                self.package(version, mode=scenario if scenario.startswith('legacy') else 'legacy',
                             source_sha=SHA if scenario == 'wrong-source' else LEGACY_SHA)
                self.run_installer('--version', version, '--no-modify-path', success=False)
                self.unchanged(before)

    def test_empty_key_cannot_promote_nested_modern_or_legacy_identity_to_root(self):
        for mode, version, source_sha in [('wrapped-identity', '0.2.0', SHA),
                                          ('legacy-wrapped-identity', LEGACY_VERSION, LEGACY_SHA)]:
            with self.subTest(mode=mode):
                before = self.sentinel()
                self.env['FIXTURE_TAG'] = version
                self.package(version, mode=mode, source_sha=source_sha)
                install = self.root / mode
                self.run_installer('--version', version, '--install-dir', str(install),
                                   '--no-modify-path', success=False)
                self.unchanged(before)
                self.assertFalse((install / 'rarus-echo').exists())

    def test_immutable_package_child_links_are_rejected(self):
        self.run_installer('--no-modify-path')
        package = self.home / ('.local/bin/.rarus-echo/packages/0.2.0-' + SHA)
        tools = package / 'libexec'
        tools.rename(self.root / 'external tools')
        tools.symlink_to(self.root / 'external tools', target_is_directory=True)
        self.run_installer('--no-modify-path', success=False)
        self.assertTrue(tools.is_symlink())

    def test_existing_immutable_package_rejects_unexpected_files(self):
        self.run_installer('--no-modify-path')
        extra = self.home / ('.local/bin/.rarus-echo/packages/0.2.0-' + SHA + '/extra.txt')
        extra.write_text('preserve conflicting content')
        self.run_installer('--no-modify-path', success=False)
        self.assertEqual(extra.read_text(), 'preserve conflicting content')

    def test_failed_rollback_retains_recovery_files(self):
        before = self.sentinel()
        real_mv = shutil.which('mv')
        self.script('mv', '''#!/bin/sh
src=$1
[ "$src" != -f ] || src=$2
case "$src" in */.env.*|*/.previous.*) exit 9;; esac
exec "''' + real_mv + '''" "$@"
''')
        result = subprocess.run(['/bin/sh', str(ROOT / 'install.sh'), '--no-modify-path'],
                                env=self.env, cwd=self.root, text=True, capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('recovery', result.stderr.lower())
        backups = list((self.home / '.local/bin').glob('.previous.*'))
        self.assertEqual(len(backups), 1)
        self.assertEqual(backups[0].read_bytes(), before['.local/bin/rarus-echo'])

    def test_prerequisite_failure(self):
        before = self.sentinel()
        isolated = self.root / 'minimal tools'
        isolated.mkdir()
        for utility in ['uname', 'cat', 'tar', 'awk', 'sed', 'grep', 'mktemp', 'cp', 'mv', 'chmod', 'mkdir', 'rm', 'ls']:
            source = self.tools / utility if (self.tools / utility).exists() else Path(shutil.which(utility))
            (isolated / utility).symlink_to(source)
        self.env['PATH'] = str(isolated)
        result = self.run_installer(success=False)
        self.assertIn('required utility missing: curl', result.stderr)
        self.unchanged(before)

    def test_cli_errors(self):
        for options in [('--version',), ('--install-dir',), ('--unknown',),
                        ('--install-dir', '/tmp/path:colon'), ('--install-dir', '/tmp/path\nnewline')]:
            self.run_installer(*options, success=False)
        self.run_installer('--help')

    @unittest.skipUnless(args.assets, 'native package supplied only in platform acceptance')
    def test_native_archive(self):
        version = args.version
        self.assertTrue(version)
        for name in ['rarus-echo-' + NATIVE_TARGET + '.tar.gz', 'SHA256SUMS']:
            shutil.copyfile(args.assets / name, self.assets / name)
        # Real releases carry a different source SHA than the fixture.
        with tarfile.open(self.assets / ('rarus-echo-' + NATIVE_TARGET + '.tar.gz')) as archive:
            metadata = json.load(archive.extractfile('build-info.json'))
        global SHA
        SHA = metadata['source_sha']
        self.addCleanup(lambda: globals().update(SHA='a' * 40))
        self.env.update(FIXTURE_OS=platform.system(), FIXTURE_ARCH=platform.machine(), FIXTURE_TAG=version)
        config = self.home / '.config/rarus-echo/config.json'
        config.parent.mkdir(parents=True, exist_ok=True)
        config.write_text('{"api_key":"11111111-1111-4111-8111-111111111111","user_id":"22222222-2222-4222-8222-222222222222"}\n')
        original_config = config.read_bytes()
        self.run_installer('--version', version)
        self.run_installer('--version', version)
        self.shells(version, self.home / '.local/bin')
        native_installs = [self.home / '.local/bin/rarus-echo']
        # Custom destination and opt-out use the same real archive.
        custom = self.root / 'native custom каталог'
        self.run_installer('--version', version, '--install-dir', str(custom), '--no-modify-path')
        native_installs.append(custom / 'rarus-echo')
        for binary in native_installs:
            reported = subprocess.check_output([str(binary), '--version', '--no-ansi'], env=self.env, cwd='/', text=True)
            self.assertEqual(reported.strip(), identity(version))
            if (version, SHA) == (LEGACY_VERSION, LEGACY_SHA):
                continue
            result = subprocess.run([sys.executable, str(ROOT / 'build/tests/installed-media-check.py'),
                                     '--binary', str(binary)], env=self.env, cwd=self.root,
                                    text=True, capture_output=True, timeout=180)
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            print(result.stdout.strip())
        self.assertEqual(config.read_bytes(), original_config)
        print('Native installer archive verified:', NATIVE_TARGET, version,
              hashlib.sha256((self.assets / ('rarus-echo-' + NATIVE_TARGET + '.tar.gz')).read_bytes()).hexdigest())


if __name__ == '__main__':
    unittest.main(argv=['shell-installer-check'], verbosity=2)
