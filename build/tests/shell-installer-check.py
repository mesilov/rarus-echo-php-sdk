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

    def package(self, version='0.2.0', target='linux-amd64', mode=None):
        name = 'rarus-echo-' + target + '.tar.gz'
        archive = self.assets / name
        files = {'rarus-echo': '#!/bin/sh\nprintf "' + identity(version) + '\\n"\n',
                 'build-info.json': json.dumps({'version': version}),
                 'runtime-sources.json': '{}',
                 'THIRD-PARTY-NOTICES.md': 'notices',
                 'RARUS-ECHO-LICENSE.txt': 'own license', 'BITRIX24-CLI-LICENSE.txt': 'upstream', 'YIIPRESS-LICENSE.txt': 'upstream',
                 'runtime-licenses/runtime.txt': 'runtime license'}
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
        with tarfile.open(archive, 'w:gz') as tar:
            for filename, value in files.items():
                data = value.encode()
                item = tarfile.TarInfo(filename)
                item.size = len(data)
                item.mode = 0o755 if filename == 'rarus-echo' else 0o644
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

    def run_installer(self, *options, success=True):
        result = subprocess.run(['/bin/sh', str(ROOT / 'install.sh'), *options], env=self.env,
                                cwd=self.root, text=True, capture_output=True)
        if success:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        else:
            self.assertNotEqual(result.returncode, 0, result.stdout)
            self.assertIn('rarus-echo installer:', result.stderr)
        self.assertFalse(list(self.root.glob('rarus-echo-install.*')))
        self.assertFalse(list((self.home / '.local/bin').glob('.rarus-echo.*')))
        self.assertFalse(list((self.home / '.local/share/rarus-echo').glob('.package.*')))
        self.assertFalse(list((self.home / '.local/share/rarus-echo').glob('.previous*')))
        self.assertFalse(list((self.home / '.local/bin').glob('.previous.*')))
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
        self.assertEqual((self.home / '.local/bin/rarus-echo').stat().st_mode & 0o777, 0o755)
        for name in ['.bashrc', '.profile', 'zsh settings/.zshrc']:
            self.assertEqual((self.home / name).read_text().count('# rarus-echo installer'), 1)
        self.shells('0.2.0', self.home / '.local/bin')
        self.env['FIXTURE_TAG'] = 'v0.3.0-rc.1'
        self.package('v0.3.0-rc.1')
        self.run_installer('--version', 'v0.3.0-rc.1')
        self.shells('v0.3.0-rc.1', self.home / '.local/bin')
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
        self.run_installer('--version', version)
        self.run_installer('--version', version)
        self.shells(version, self.home / '.local/bin')
        # Custom destination and opt-out use the same real archive.
        self.run_installer('--version', version, '--install-dir', str(self.root / 'native custom bin'), '--no-modify-path')
        print('Native installer archive verified:', NATIVE_TARGET, version,
              hashlib.sha256((self.assets / ('rarus-echo-' + NATIVE_TARGET + '.tar.gz')).read_bytes()).hexdigest())


if __name__ == '__main__':
    unittest.main(argv=['shell-installer-check'], verbosity=2)
