"""Owned native media process selection and failure cleanup contracts."""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch


class ProcessFixtureTests(unittest.TestCase):
    def helpers(self):
        path = Path(__file__).with_name('process_fixtures.py')
        self.assertTrue(path.is_file(), 'native process ownership helpers are required')
        import process_fixtures
        return process_fixtures

    def test_selects_exact_bundled_ffmpeg_and_reports_console_host(self):
        helpers = self.helpers()
        with tempfile.TemporaryDirectory() as temp:
            executable = Path(temp) / 'libexec/ffmpeg.exe'
            executable.parent.mkdir()
            executable.write_bytes(b'fixture executable identity')
            rows = [{'pid': 101, 'parent_pid': 100, 'name': 'conhost.exe', 'executable': 'C:/Windows/System32/conhost.exe'},
                    {'pid': 102, 'parent_pid': 100, 'name': 'ffmpeg.exe', 'executable': str(executable)}]
            selected = helpers.owned_media_child(rows, 100, executable)
            self.assertEqual(102, selected['pid'])
            for invalid in [rows[:1], rows + [dict(rows[1], pid=103)],
                            [dict(rows[1], parent_pid=999)], [dict(rows[1], executable=None)]]:
                with self.assertRaisesRegex(RuntimeError, 'snapshot=') as error:
                    helpers.owned_media_child(invalid, 100, executable)
                self.assertIn('parent_pid', str(error.exception))

    def test_matching_name_at_another_path_is_not_owned_media(self):
        helpers = self.helpers()
        with tempfile.TemporaryDirectory() as temp:
            expected, impostor = Path(temp) / 'bundle/ffmpeg.exe', Path(temp) / 'other/ffmpeg.exe'
            for path in [expected, impostor]:
                path.parent.mkdir()
                path.write_bytes(b'different files')
            with self.assertRaisesRegex(RuntimeError, 'snapshot='):
                helpers.owned_media_child([{'pid': 2, 'parent_pid': 1, 'name': 'ffmpeg.exe',
                                            'executable': str(impostor)}], 1, expected)

    def test_workspace_cleanup_error_does_not_replace_primary_failure(self):
        helpers = self.helpers()
        class Directory:
            name = 'fixture retained directory'
            def cleanup(self):
                raise PermissionError('locked fixture')
        with patch.object(helpers.tempfile, 'TemporaryDirectory', return_value=Directory()):
            with self.assertRaisesRegex(RuntimeError, 'primary process diagnostic') as error:
                with helpers.acceptance_directory(prefix='fixture-'):
                    raise RuntimeError('primary process diagnostic')
            self.assertIn('locked fixture', '\n'.join(error.exception.__notes__))

    def test_failed_fixture_stops_owned_tree_and_reaps_inherited_pipes(self):
        helpers = self.helpers()
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            owned = root / 'owned'
            owned.mkdir()
            ready = root / 'ready.json'
            child = "import sys,time; f=open(sys.argv[1],'wb'); f.write(b'partial'); f.flush(); time.sleep(60)"
            parent = """import json,pathlib,subprocess,sys,time
path, ready = map(pathlib.Path, sys.argv[1:3])
child = subprocess.Popen([sys.executable, '-c', sys.argv[3], str(path)])
deadline = time.monotonic() + 5
while not path.exists() and time.monotonic() < deadline: time.sleep(0.02)
ready.write_text(json.dumps({'pid':child.pid}))
time.sleep(60)
"""
            process = subprocess.Popen([sys.executable, '-c', parent, str(owned / 'partial.flac'), str(ready), child],
                                       stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            original = RuntimeError('primary fixture failure')
            try:
                deadline = time.monotonic() + 8
                while not ready.exists() and process.poll() is None and time.monotonic() < deadline:
                    time.sleep(0.02)
                self.assertTrue(ready.exists())
                snapshot = helpers.child_snapshot(process.pid)
                self.assertEqual(1, len(snapshot))
                # Homebrew's Python launcher re-execs a different framework image.
                # This Python-only teardown test targets that actual child image;
                # native FFmpeg acceptance still requires its exact bundled path.
                executable = Path(snapshot[0]['executable'])
                media = helpers.owned_media_child(snapshot, process.pid, executable)
                handles = helpers.open_media_handles([media], executable)
                try:
                    helpers.cleanup_failed_fixture(process, executable, snapshot, handles, original, owned)
                    self.assertTrue(helpers.media_handles_exited(handles, 2000))
                finally:
                    helpers.close_media_handles(handles)
                self.assertIsNotNone(process.poll())
                self.assertFalse(owned.exists())
                self.assertEqual([], getattr(original, '__notes__', []))
            finally:
                if process.poll() is None:
                    if os.name == 'nt':
                        taskkill = Path(os.environ['SystemRoot']) / 'System32/taskkill.exe'
                        subprocess.run([str(taskkill), '/PID', str(process.pid), '/T', '/F'],
                                       capture_output=True, timeout=5)
                    elif ready.exists():
                        try:
                            os.kill(json.loads(ready.read_text())['pid'], 9)
                        except ProcessLookupError:
                            pass
                    process.kill()
                    process.communicate(timeout=5)


if __name__ == '__main__':
    unittest.main()
