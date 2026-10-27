"""Build logs preserve Unicode under Windows-style CP1252 Python stdio."""
import os
from pathlib import Path
import subprocess
import sys
import unittest

BUILD = Path(__file__).resolve().parents[1]
TEXT = 'каталог с пробелом — 音声'


class BuildLoggingTests(unittest.TestCase):
    def script(self, code, *, utf8_startup=False):
        return subprocess.run([sys.executable, '-c', code], cwd=BUILD.parent,
                              env=os.environ | {'PYTHONIOENCODING': 'utf-8:backslashreplace' if utf8_startup else 'cp1252',
                                                'PYTHONUTF8': '1' if utf8_startup else '0'},
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                              encoding='utf-8', errors='strict', timeout=30)

    def test_media_runner_logs_unicode_arguments_and_captures_utf8_child(self):
        code = f'''import os, sys
assert sys.stdout.encoding.lower() == 'cp1252'
sys.path.insert(0, {str(BUILD)!r})
from media.toolkit import run
text = {TEXT!r}
child = "import sys; print(sys.stdout.encoding); print(sys.argv[1])"
result = run([sys.executable, '-c', child, text], env=os.environ | {{'PYTHONIOENCODING': 'cp1252', 'PYTHONUTF8': '0'}})
assert result.splitlines() == ['utf-8', text], repr(result)
print('captured: ' + result)
print(text, file=sys.stderr)
'''
        result = self.script(code)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn(TEXT, result.stdout)
        self.assertIn(TEXT, result.stderr)

    def test_runtime_parent_and_package_error_streams_preserve_unicode(self):
        for module in ['runtime', 'package']:
            with self.subTest(module=module):
                code = f'''import os, sys
assert sys.stdout.encoding.lower() == {'utf-8' if module == 'runtime' else 'cp1252'!r}
sys.path.insert(0, {str(BUILD)!r})
import {module}
text = {TEXT!r}
{module}.run([sys.executable, '-c', "import sys; print(sys.argv[1])", text],
             env=os.environ.copy())
print(text, file=sys.stderr)
'''
                result = self.script(code, utf8_startup=module == 'runtime')
                self.assertEqual(0, result.returncode, result.stderr)
                self.assertIn(TEXT, result.stdout)
                self.assertIn(TEXT, result.stderr)

    def test_package_runtime_failure_diagnostics_preserve_unicode_and_redact_token(self):
        code = f'''import os, pathlib, subprocess, sys, tempfile
sys.path.insert(0, {str(BUILD)!r})
import package, runtime
with tempfile.TemporaryDirectory() as temp:
    log = pathlib.Path(temp) / 'log'
    log.mkdir()
    (log / 'spc.shell.log').write_bytes(({TEXT!r} + ' fixture-secret').encode('utf-8'))
    try:
        runtime.run([sys.executable, '-c', 'raise SystemExit(7)'], cwd=temp,
                    env=os.environ | {{'GITHUB_TOKEN': 'fixture-secret'}})
    except subprocess.CalledProcessError as error:
        assert error.returncode == 7
        print('verified runtime failure 7')
    else:
        raise AssertionError('Runtime failure was lost')
'''
        result = self.script(code)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn(TEXT, result.stderr)
        self.assertIn('[redacted]', result.stderr)
        self.assertNotIn('fixture-secret', result.stdout + result.stderr)

    def test_non_utf8_native_diagnostic_keeps_bytes_and_original_failure(self):
        code = f'''import subprocess, sys
sys.path.insert(0, {str(BUILD)!r})
from media.toolkit import run
try:
    run([sys.executable, '-c', "import sys; sys.stdout.buffer.write(bytes([98, 97, 100, 233, 10])); sys.exit(7)"])
except subprocess.CalledProcessError as error:
    assert error.returncode == 7
    assert r'bad\\xe9' in error.output, repr(error.output)
    print('verified native failure 7')
else:
    raise AssertionError('Native command failure was lost')
'''
        result = self.script(code)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn(r'bad\xe9', result.stdout)
        self.assertIn('verified native failure 7', result.stdout)


if __name__ == '__main__':
    unittest.main()
