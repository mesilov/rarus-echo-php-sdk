"""Native acceptance process ownership and bounded failure teardown."""
from contextlib import contextmanager
import ctypes
import json
import os
from pathlib import Path
import shutil
import signal
import subprocess
import sys
import tempfile


@contextmanager
def acceptance_directory(**options):
    directory = tempfile.TemporaryDirectory(**options)
    try:
        yield directory.name
    except BaseException as primary:
        try:
            directory.cleanup()
        except Exception as cleanup:
            primary.add_note('Acceptance directory cleanup failed at ' + directory.name + ': ' + str(cleanup))
        raise
    else:
        directory.cleanup()


def same_executable(actual, expected):
    if not actual:
        return False
    try:
        return os.path.samefile(actual, expected)
    except OSError:
        return False


def child_snapshot(pid):
    if os.name == 'nt':
        powershell = Path(os.environ['SystemRoot']) / 'System32/WindowsPowerShell/v1.0/powershell.exe'
        script = """[Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
$rows = @(Get-CimInstance Win32_Process -Filter 'ParentProcessId = %d' | ForEach-Object {
    [pscustomobject]@{pid = $_.ProcessId; parent_pid = $_.ParentProcessId; name = $_.Name;
        executable = $_.ExecutablePath; created = $_.CreationDate.ToUniversalTime().ToString('o')}
})
ConvertTo-Json -InputObject @($rows) -Compress
""" % pid
        output = subprocess.check_output([str(powershell), '-NoProfile', '-NonInteractive', '-Command', script],
                                         encoding='utf-8', timeout=10)
        rows = json.loads(output)
        if not isinstance(rows, list):
            raise RuntimeError('Windows child snapshot was not an array: ' + output)
        return sorted(rows, key=lambda row: row['pid'])
    if sys.platform.startswith('linux'):
        children = Path('/proc/' + str(pid) + '/task/' + str(pid) + '/children').read_text().split()
        rows = []
        for child in children:
            path = Path('/proc') / child
            status = dict(line.split(':', 1) for line in (path / 'status').read_text().splitlines() if ':' in line)
            rows.append({'pid': int(child), 'parent_pid': int(status['PPid']), 'name': status['Name'].strip(),
                         'executable': os.readlink(path / 'exe')})
        return rows
    output = subprocess.check_output(['/bin/ps', '-axo', 'pid=,ppid=,comm='], text=True)
    library = ctypes.CDLL('/usr/lib/libproc.dylib', use_errno=True)
    library.proc_pidpath.argtypes = [ctypes.c_int, ctypes.c_void_p, ctypes.c_uint32]
    library.proc_pidpath.restype = ctypes.c_int
    rows = []
    for line in output.splitlines():
        parts = line.strip().split(None, 2)
        if len(parts) != 3 or int(parts[1]) != pid:
            continue
        buffer = ctypes.create_string_buffer(4096)
        length = library.proc_pidpath(int(parts[0]), buffer, len(buffer))
        rows.append({'pid': int(parts[0]), 'parent_pid': pid, 'name': parts[2],
                     'executable': os.fsdecode(buffer.value) if length > 0 else None})
    return rows


def owned_media_child(snapshot, parent_pid, executable):
    matches = [row for row in snapshot if row['parent_pid'] == parent_pid
               and same_executable(row.get('executable'), executable)]
    if len(matches) != 1:
        raise RuntimeError('Expected exactly one owned bundled FFmpeg; parent=' + str(parent_pid)
                           + ', expected=' + str(executable) + ', snapshot='
                           + json.dumps(snapshot, ensure_ascii=False, sort_keys=True))
    return matches[0]


def windows_kernel():
    kernel = ctypes.WinDLL('kernel32', use_last_error=True)
    handle = ctypes.c_void_p
    kernel.OpenProcess.argtypes = [ctypes.c_uint32, ctypes.c_int, ctypes.c_uint32]
    kernel.OpenProcess.restype = handle
    kernel.QueryFullProcessImageNameW.argtypes = [handle, ctypes.c_uint32, ctypes.c_wchar_p,
                                                ctypes.POINTER(ctypes.c_uint32)]
    kernel.QueryFullProcessImageNameW.restype = ctypes.c_int
    kernel.WaitForSingleObject.argtypes = [handle, ctypes.c_uint32]
    kernel.WaitForSingleObject.restype = ctypes.c_uint32
    kernel.TerminateProcess.argtypes = [handle, ctypes.c_uint32]
    kernel.TerminateProcess.restype = ctypes.c_int
    kernel.CloseHandle.argtypes = [handle]
    kernel.CloseHandle.restype = ctypes.c_int
    kernel.AttachConsole.argtypes = [ctypes.c_uint32]
    kernel.AttachConsole.restype = ctypes.c_int
    kernel.GenerateConsoleCtrlEvent.argtypes = [ctypes.c_uint32, ctypes.c_uint32]
    kernel.GenerateConsoleCtrlEvent.restype = ctypes.c_int
    return kernel


def open_media_handles(rows, executable):
    handles = []
    if os.name != 'nt':
        return handles
    kernel = windows_kernel()
    try:
        for row in rows:
            process = kernel.OpenProcess(0x00100000 | 0x1000 | 0x0001, False, row['pid'])
            if not process:
                raise ctypes.WinError(ctypes.get_last_error())
            handles.append((kernel, process, row['pid']))
            buffer, size = ctypes.create_unicode_buffer(32768), ctypes.c_uint32(32768)
            if not kernel.QueryFullProcessImageNameW(process, 0, buffer, ctypes.byref(size)):
                raise ctypes.WinError(ctypes.get_last_error())
            if not same_executable(buffer.value, executable):
                raise RuntimeError('Native media PID changed executable identity: ' + str(row['pid']))
        return handles
    except BaseException:
        close_media_handles(handles)
        raise


def media_handles_exited(handles, timeout):
    return all(kernel.WaitForSingleObject(handle, timeout) == 0 for kernel, handle, pid in handles)


def close_media_handles(handles):
    for kernel, handle, pid in handles:
        kernel.CloseHandle(handle)
    handles.clear()


def cleanup_failed_fixture(process, executable, snapshot, handles, primary, owned, temporary=None):
    def attempt(label, action):
        try:
            action()
        except Exception as error:
            primary.add_note(label + ': ' + str(error))
    if os.name == 'nt':
        # Stop the entire still-owned tree before killing/reaping only its root.
        if process.poll() is None:
            taskkill = Path(os.environ['SystemRoot']) / 'System32/taskkill.exe'
            def stop_tree():
                result = subprocess.run([str(taskkill), '/PID', str(process.pid), '/T', '/F'],
                                        capture_output=True, encoding='utf-8', errors='backslashreplace', timeout=5)
                if result.returncode and process.poll() is None:
                    raise RuntimeError(result.stdout + result.stderr)
            attempt('Owned process-tree termination failed', stop_tree)
        for kernel, handle, pid in handles:
            if kernel.WaitForSingleObject(handle, 0) != 0:
                attempt('Owned media termination failed', lambda: kernel.TerminateProcess(handle, 1))
                attempt('Owned media wait failed', lambda: kernel.WaitForSingleObject(handle, 3000))
    else:
        # Do not signal stale snapshot PIDs: query current PPID and executable
        # while the owned root is still alive, then stop its matching direct child.
        current = []
        if process.poll() is None:
            try:
                current = child_snapshot(process.pid)
            except Exception as error:
                primary.add_note('Current owned-child inspection failed: ' + str(error))
        for row in current:
            if row['parent_pid'] == process.pid and same_executable(row.get('executable'), executable):
                attempt('Owned media termination failed', lambda row=row: os.kill(row['pid'], signal.SIGKILL))
    if process.poll() is None:
        attempt('Fixture root termination failed', process.kill)
    attempt('Fixture root wait failed', lambda: process.wait(timeout=5))
    attempt('Fixture pipe drain failed', lambda: process.communicate(timeout=3))
    if owned.exists():
        attempt('Owned partial output cleanup failed', lambda: shutil.rmtree(owned))
    if temporary is not None:
        for directory in Path(temporary).glob('rarus-echo-process-*'):
            attempt('Owned media log cleanup failed', lambda directory=directory: shutil.rmtree(directory))
