# Standalone distribution notices

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
sources are recorded in runtime-sources.json. No executable compressor is used.

## PHAR bootstrap and PHP micro patches

The embedded PHAR launcher and PHP micro patches are adapted from
mesilov/bitrix24-cli, commit f23ca85fe45b003cedd0b6fd0340da6ac850ba00:
https://github.com/mesilov/bitrix24-cli

Its packaging pipeline derives from YiiPress engine, commit
e61459fc788a74b91188d5d41a5fca59a02a229e:
https://github.com/yiipress/engine

Neither project is an application dependency. The retained upstream notices
below cover the derived portions of the launcher/runtime support. Build-only
helper provenance is documented separately in build/PACKAGING-NOTICES.md in
the source repository.

### bitrix24-cli terms

bitrix24-cli-license
Based on the BSD 3-Clause License (SPDX: BSD-3-Clause).

Copyright (c) 2026 Maksim Mesilov <mesilov.maxim@gmail.com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

 * Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in
   the documentation and/or other materials provided with the
   distribution.
 * Neither the name of the copyright holder nor the names of its
   contributors may be used to endorse or promote products derived
   from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

All trademarks, service marks, trade names and product names mentioned
in this software and its documentation belong to their respective owners.

### YiiPress terms

Copyright © 2016 by Alexander Makarov
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

 * Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in
   the documentation and/or other materials provided with the
   distribution.
 * Neither the name of Yii Software nor the names of its
   contributors may be used to endorse or promote products derived
   from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
