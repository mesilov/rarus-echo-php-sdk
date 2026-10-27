#!/usr/bin/env python3
"""Reject accidental dependencies on a build host's PHP/compiler libraries.

Adapted from bitrix24-cli; see build/THIRD-PARTY-NOTICES.md.
"""
import os
from pathlib import Path
import platform
import re
import subprocess
import sys

binary = str(Path(sys.argv[1]).resolve())
system = platform.system()
if system == 'Linux':
    headers = subprocess.check_output(['readelf', '-l', binary], text=True)
    dynamic = subprocess.check_output(['readelf', '-d', binary], text=True)
    if 'INTERP' in headers or '(NEEDED)' in dynamic:
        raise AssertionError('Linux package requires a dynamic linker or shared library')
    print(subprocess.check_output(['file', binary], text=True), end='')
elif system == 'Darwin':
    output = subprocess.check_output(['otool', '-L', binary], text=True)
    for line in output.splitlines()[1:]:
        dependency = line.strip().split(' (', 1)[0]
        if not dependency.startswith(('/usr/lib/', '/System/Library/')):
            raise AssertionError('Non-system macOS library: ' + dependency)
    print(output, end='')
elif system == 'Windows':
    output = subprocess.check_output(['dumpbin', '/dependents', binary], text=True)
    dependencies = re.findall(r'^\s+([\w.-]+\.dll)\s*$', output, re.MULTILINE | re.IGNORECASE)
    if not dependencies:
        raise AssertionError('No Windows import table was inspected')
    for name in dependencies:
        if name.lower().startswith(('vcruntime', 'msvcp', 'php', 'libphp')):
            raise AssertionError('External runtime dependency: ' + name)
        if not name.lower().startswith(('api-ms-win-', 'ext-ms-win-')) and not (Path(os.environ['SystemRoot']) / 'System32' / name).is_file():
            raise AssertionError('Non-system Windows library: ' + name)
    print(output, end='')
else:
    raise RuntimeError('Unsupported platform: ' + system)
print('Linkage contains only supported operating-system dependencies')
