# Packaging attribution

Packaging helpers, the POSIX installer and its fixture tests, and PHP micro patches are adapted from mesilov/bitrix24-cli,
commit f23ca85fe45b003cedd0b6fd0340da6ac850ba00:
https://github.com/mesilov/bitrix24-cli

Copyright (c) 2026 Maksim Mesilov <mesilov.maxim@gmail.com>.
The complete original terms are in BITRIX24-CLI-LICENSE.txt.

That implementation derives its PHAR/PHP micro pipeline from YiiPress engine,
commit e61459fc788a74b91188d5d41a5fca59a02a229e:
https://github.com/yiipress/engine
Its BSD-3-Clause terms and copyright are in YIIPRESS-LICENSE.txt.

RARUS Echo PHP SDK is MIT licensed; see RARUS-ECHO-LICENSE.txt in platform archives.
Production Composer package licenses remain inside the embedded PHAR.
PHP and compiled library licenses are exported to runtime-licenses/.
The PHP micro license is included there as phpmicro-LICENSE.txt.
Linux archives additionally carry the actual musl source COPYRIGHT and GCC
13.2.0 COPYING3 / COPYING.RUNTIME (copied verbatim from gcc-mirror/gcc,
releases/gcc-13.2.0). GCC Runtime Library Exception applies to the compiler runtime.

Toolchain: https://github.com/crazywhalecc/static-php-cli (MIT).
PHP micro: https://github.com/static-php/phpmicro (Apache-2.0).
Pins and patch SHA256 values are in build-info.json; exact downloaded runtime
sources are recorded in runtime-sources.json.

Executable compression is also adapted from the b24cli reference above:
- UPX 5.2.1: https://github.com/upx/upx/releases/tag/v5.2.1
- wrappe 1.0.4 (BSD-2-Clause): https://github.com/Systemcluster/wrappe

Pinned download/source hashes and patches are recorded in compression-info.json.
Applicable UPX notices or wrappe and Rust dependency notices accompany compressed
archives in compression-licenses/. FFmpeg/ffprobe are not packed.
