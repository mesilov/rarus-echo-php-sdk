"""UTF-8 build logs and Python-child I/O, independent of the host code page."""
import os
import sys


def utf8_environment(environment=None):
    result = dict(os.environ if environment is None else environment)
    result['PYTHONIOENCODING'] = 'utf-8:backslashreplace'
    result['PYTHONUTF8'] = '1'
    return result


def configure_build_console():
    for stream in (sys.stdout, sys.stderr):
        if hasattr(stream, 'reconfigure'):
            # Real Unicode is retained. A malformed filesystem surrogate remains
            # visible as an escape instead of turning logging into a build failure.
            stream.reconfigure(encoding='utf-8', errors='backslashreplace')
    os.environ.update(utf8_environment({}))
