#!/usr/bin/env python3
"""Finalize PHP micro payload bounds before signing a thin arm64 Mach-O."""
import argparse
from pathlib import Path
import struct
import subprocess

LC_SEGMENT_64 = 0x19
LC_CODE_SIGNATURE = 0x1d
PAGE_SIZE = 0x4000


def layout(data, require_metadata=True):
    if len(data) < 32 or struct.unpack_from('<II', data) != (0xfeedfacf, 0x100000c):
        raise ValueError('Expected a thin arm64 Mach-O executable')
    if struct.unpack_from('<I', data, 12)[0] != 2:
        raise ValueError('Expected MH_EXECUTE')
    count, size = struct.unpack_from('<II', data, 16)
    end = 32 + size
    if end > len(data):
        raise ValueError('Truncated Mach-O load commands')
    commands, linkedit, signature, metadata = [], None, None, None
    offset = 32
    for _ in range(count):
        if offset + 8 > end:
            raise ValueError('Truncated Mach-O load command')
        command, length = struct.unpack_from('<II', data, offset)
        if length < 8 or length % 8 or offset + length > end:
            raise ValueError('Invalid Mach-O load command size')
        commands.append((offset, length))
        if command == LC_SEGMENT_64:
            if length < 72:
                raise ValueError('Truncated Mach-O segment')
            name = data[offset + 8:offset + 24].rstrip(b'\0')
            address, virtual_size, file_offset, file_size = struct.unpack_from('<QQQQ', data, offset + 24)
            sections = struct.unpack_from('<I', data, offset + 64)[0]
            if length != 72 + sections * 80 or file_offset + file_size > len(data):
                raise ValueError('Invalid Mach-O segment bounds')
            if name == b'__LINKEDIT':
                if linkedit is not None or sections:
                    raise ValueError('Unexpected __LINKEDIT layout')
                linkedit = (offset, file_offset, file_size)
            for i in range(sections):
                section = offset + 72 + i * 80
                if data[section:section + 16].rstrip(b'\0') != b'__micro_sfxsize':
                    continue
                section_address, section_size, section_offset = struct.unpack_from('<QQI', data, section + 32)
                flags = struct.unpack_from('<I', data, section + 64)[0]
                if (metadata is not None or name != b'__DATA'
                        or data[section + 16:section + 32].rstrip(b'\0') != b'__DATA'
                        or section_size != 16 or flags & 0xff
                        or not file_offset <= section_offset <= file_offset + file_size - 16
                        or section_address != address + section_offset - file_offset
                        or section_address + 16 > address + virtual_size):
                    raise ValueError('Invalid micro payload metadata section')
                metadata = section_offset
        elif command == LC_CODE_SIGNATURE:
            if signature is not None or length != 16:
                raise ValueError('Unexpected Mach-O signature command')
            signature = (offset, *struct.unpack_from('<II', data, offset + 8))
        offset += length
    if offset != end or linkedit is None or signature is None or (require_metadata and metadata is None):
        raise ValueError('Missing or inconsistent Mach-O payload/signature layout')
    _, start, length = signature
    if length == 0 or start < linkedit[1] or start + length != linkedit[1] + linkedit[2]:
        raise ValueError('Code signature must terminate __LINKEDIT')
    if metadata is not None and (metadata < end or metadata + 16 > linkedit[1]):
        raise ValueError('Metadata overlaps Mach-O headers or __LINKEDIT')
    return commands, linkedit, signature, metadata


def verify(binary, payload):
    data = Path(binary).read_bytes()
    _, linkedit, signature, metadata = layout(data)
    start, end = struct.unpack_from('>QQ', data, metadata)
    if not linkedit[1] <= start < end <= signature[1] or data[start:end] != Path(payload).read_bytes():
        raise ValueError('Signed micro payload bounds or bytes differ from the original PHAR')
    if signature[1] + signature[2] != len(data):
        raise ValueError('Unsigned data follows the final Mach-O signature')
    subprocess.run(['codesign', '--verify', '--strict', '--verbose=2', str(binary)], check=True)


def finalize(binary, payload):
    binary, payload = Path(binary), Path(payload)
    data = bytearray(binary.read_bytes())
    original = payload.read_bytes()
    commands, linkedit, signature, metadata = layout(data)
    command_offset, signature_start, signature_size = signature
    payload_start, payload_end = signature_start + signature_size, len(data)
    if not original or data[payload_start:payload_end] != original:
        raise ValueError('Combined file does not contain exactly the original PHAR after the runtime')
    if data[metadata:metadata + 16] != bytes(16):
        raise ValueError('Compiled micro payload metadata must be an empty placeholder')
    # Retain the original runtime size/payload offset. Obsolete signature bytes
    # become inert padding; deleting them would move the PHAR under the loader.
    data[signature_start:payload_start] = bytes(signature_size)
    struct.pack_into('>QQ', data, metadata, payload_start, payload_end)
    load_commands = b''.join(data[offset:offset + size] for offset, size in commands
                             if offset != command_offset)
    old_commands_size = struct.unpack_from('<I', data, 20)[0]
    data[32:32 + old_commands_size] = load_commands + bytes(16)
    struct.pack_into('<II', data, 16, len(commands) - 1, len(load_commands))
    # Locate __LINKEDIT again after removing a possibly preceding command.
    new_linkedit = linkedit[0] - (16 if command_offset < linkedit[0] else 0)
    file_size = payload_end - linkedit[1]
    struct.pack_into('<Q', data, new_linkedit + 32, (file_size + PAGE_SIZE - 1) // PAGE_SIZE * PAGE_SIZE)
    struct.pack_into('<Q', data, new_linkedit + 48, file_size)
    binary.write_bytes(data)
    subprocess.run(['codesign', '--force', '--sign', '-', '--identifier', 'rarus-echo', str(binary)], check=True)
    verify(binary, payload)
