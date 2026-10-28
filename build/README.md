# Standalone CLI packaging

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/53

The build uses the production lock at docker/rarus-echo-cli/composer.lock.
build/config.json pins PHP 8.5.11, static-php-cli and PHP micro commits, extensions,
and reviewed patches. build-info.json records the version, full source SHA,
lock SHA256, runtime pins and patch SHA256 values. runtime-sources.json records
SPC's downloaded source lock. Compiler caches live under ignored runtime/. Hashed inputs are checked out with LF
endings on every platform; a regression test covers core.autocrlf=true.

The starting point is mesilov/bitrix24-cli at
f23ca85fe45b003cedd0b6fd0340da6ac850ba00. See PACKAGING-NOTICES.md and
the original license files. Echo adds fileinfo and native Linux arm64.
There is no executable compressor.

## Local contracts and production PHAR

Run from a clean committed checkout (ignored vendor and runtime caches are fine):

```bash
python3 -m unittest discover -s build/tests -v
python3 build/source.py --source-sha "$(git rev-parse HEAD)"
python3 build/package.py phar --source-sha "$(git rev-parse HEAD)"
php dist/phar/rarus-echo.phar --version
```

Building a PHAR requires PHP 8.4/8.5 with the production Composer extensions,
Phar, zlib, and Composer. Build commands install only locked production
dependencies with plugins/scripts disabled; no local vendor files or .env
files enter staging. PHAR construction validates the installed package set.
The PHAR itself is an intermediate build artifact.

## Native executables

All targets require Python >=3.11, PHP 8.5.11, Composer 2.10.3, Git, network
access to the pinned toolchain/source archives, and a native C/C++ compiler.
The GitHub Actions workflow supplies the platform-specific prerequisites.

- Linux: use docker/standalone/Dockerfile on native amd64 or arm64. It builds
  a musl runtime and tests its archive before exporting it.
- macOS arm64: install Xcode command-line tools, autoconf, automake, bison,
  re2c, pkgconf and cmake; put Homebrew bison on PATH.
- Windows amd64: use VS 2022 C++ build tools with an x64 developer environment.
  Composer's composer.phar must sit beside its launcher.

```bash
python3 build/package.py macos-arm64 --source-sha "$(git rev-parse HEAD)"
# Native Windows PowerShell:
# python build/package.py windows-amd64 --source-sha (git rev-parse HEAD)

# Validates source and exits before Docker on dirty/mismatched input:
bash build/package-linux.sh
```

For release builds pass --version 0.5.0-rc-1 (or ECHO_RELEASE_VERSION); the workflow
takes it from the pushed tag. Untagged builds use dev-<full SHA>.
Docker's context omits Git, so the workflow validates the real checkout first
and forwards that exact SHA to the build. Runtime compilation is intentionally
native; no emulation or cross-compilation is required.

package.py makes the final platform archive under dist/assets and runs
build/tests/smoke.py against the extracted archive. It copies the executable
to a Cyrillic/spaced temporary directory, clears PATH and real credentials,
checks the embedded runtime probe, exercises help and invalid inputs,
reads current-directory .env and submits a generated WAV to a local HTTP server.
A linkage gate rejects Linux dynamic imports, non-system macOS libraries, and external Windows PHP/VC runtime DLLs.
Linux CI repeats archive acceptance in a container with Python and no PHP.
The probe is an acceptance artifact and is not shipped to users.

## Release gate

The Standalone CLI workflow runs on PRs into dev/main, workflow dispatch, and
version-tag pushes. All four native jobs must pass before the aggregate job
validates archive contents, matching identities and exactly four SHA256SUMS
entries. PRs and branch dispatches upload only workflow artifacts.
Only a tag-push event can create/update its GitHub Release.
Tags with a prerelease suffix create a prerelease and do not replace Latest.

Build artifacts:
- rarus-echo-linux-amd64.tar.gz
- rarus-echo-linux-arm64.tar.gz
- rarus-echo-macos-arm64.tar.gz
- rarus-echo-windows-amd64.zip
- SHA256SUMS

Each archive contains the executable, build-info.json, runtime-sources.json,
application/runtime notices and runtime-licenses/. Unix mode is 0755.
Files outside that allowlist (PHARs, probes, vendor directories, local config)
are excluded from final archives. Missing notices or a mismatched identity
fail the build. SHA256SUMS hashes the final compressed archive bytes.

macOS uses an ad-hoc signature that covers the embedded PHAR; it is not
notarized. Windows binaries are not Authenticode signed. Windows temporarily
extracts the embedded PHAR to a random private temporary directory, removed at
shutdown, to work around PHP micro's stream handling. Cleanup rechecks the file
after Phar unlink and falls back to PHP filesystem deletion for Unicode paths.

Completed native runtime caches are saved even if subsequent archive acceptance
fails; failed acceptance still prevents artifact upload and publication.

Updating PHP, SPC, micro, extensions or patches invalidates runtime caches.
After such updates, rerun all native jobs and inspect runtime-sources.json
and the license dump. Toolchain pins do not guarantee bit-for-bit reproducibility.

## Bundled media toolkit (#60)

Every native archive also contains `libexec/ffmpeg[.exe]`,
`libexec/ffprobe[.exe]`, `media-build-info.json`, `media-SHA256SUMS`,
`media-licenses/`, and compressed `media-source.tar.xz`. All three executables
have mode 0755 on Unix; notices, metadata and source have mode 0644. Archives
contain regular files only. Validation rejects duplicate/case-colliding paths,
links, traversal, unexpected members, target/source/profile drift, tampered
binaries/source and a media manifest that does not cover exactly the two tools,
metadata and source package. All four platform names and release SHA256SUMS
remain unchanged.

`build/media/config.json` pins signed upstream FFmpeg 9.0.2 and the common compact
profile. `build/media/README.md` explains source signature verification, native
prerequisites and the offline source recipe. The toolkit has audio codecs,
container inspection and timeline filters; no video encoders/decoders, capture,
playback, network protocols or GPL/nonfree libraries. Linux is static musl;
macOS permits only system dependencies and signs both tools; Windows uses UCRT64
with static compiler runtime and checked system DLL imports. MSYS2 is confined
to the media build environment so its `link.exe` cannot shadow MSVC for PHP.
Native acceptance remains required on Linux amd64/arm64, macOS arm64 (macOS 15)
and Windows amd64 (Server 2022; documented desktop floor Windows 11).
Build entrypoints use UTF-8 console and Python-child I/O so Unicode acceptance
paths work under Windows CP1252 as well. Foreign native diagnostic bytes remain
visible as escapes and a child failure keeps its original exit status. Target
cache fallbacks recheck the complete PHP runtime-input marker; media caches
still verify their own source/profile/compiler/recipe identity. Linux BuildKit
cache namespaces are stable per target; layer input hashes and the native
acceptance checks still invalidate and reject changed or incompatible inputs.

```bash
python3 build/media/build.py macos-arm64 --destination runtime/media-macos-arm64
python3 build/media/accept.py runtime/media-macos-arm64 --target macos-arm64 --linkage
python3 build/media/archive_size.py dist/assets/rarus-echo-macos-arm64.tar.gz
```

Use `--source /absolute/path/ffmpeg-9.0.2.tar.xz` for offline compilation.
Missing files never trigger a runtime download. The source package includes the
exact upstream archive, signature/key, complete recipe/notices and effective
build arguments. Cache identities cover source, common profile, target,
compiler/OS and all recipe inputs. Cached manifests and both executable hashes
are verified; native corpus acceptance runs again before distribution.

Native package smoke copies the whole bundle into a directory with spaces and
Cyrillic characters, clears PATH, changes cwd, probes/copies/transcodes the
committed CC0 fixtures and submits video to a controlled local multipart server.
It proves audio-only bytes reach that server, channel/rate preservation,
unchanged source bytes and owned temporary cleanup. It also retains the previous
ordinary-audio/runtime/configuration checks. Real Echo format evidence is kept
separately under `tests/Fixtures/Media/echo-acceptance.json`.
FLAC fallback explicitly preserves supported integer precision through 24 bits;
source float PCM and lossless depths above 24 bits or unknown depth fail before
upload when conversion is required. Copy routes retain the encoded packets.

Each build records `media-size-report.json` and CI uploads per-target size
reports. The named baseline is the `7f41160` no-media archive layout with the same
CLI/runtime payload and every media file omitted, which isolates source/notices
and both binaries. A complete compressed increment above 25 MiB fails. Docker
measures the complete media layer's gzip bytes and its total uncompressed delta
against the named `cli-baseline` image target, before registry publication.
Uncompressed measurements sum decoded saved-layer tar stream bytes, including
headers/padding; engine-reported sizes are recorded separately because some
Docker/containerd versions report compressed blobs in that field.

The default Docker image builds the same profile in a separate compiler stage
and copies only the accepted payload to `/opt/rarus-echo/media`. The final image
retains no compiler or expanded source tree. Native Docker jobs build/load local
candidates, run offline read-only-mount acceptance and size gates, then preserve
those exact image bytes. Publication requires both native jobs to pass before
pushing either platform and creating the multi-architecture tag. Media inputs
and fixtures trigger both workflows. Standalone publication also requires
complete-package installer acceptance on every native target.

Keep the adjacent files together when extracting a portable archive. The bare
CLI supports commands that do not use media, but cannot prepare video alone.
Installed packages live under `.rarus-echo/packages/<version>-<full-source-sha>/`;
the matching executable remains at the chosen install directory's root. Prior
immutable packages are retained so running commands keep their matching tools;
remove an old package manually only after all commands using it have exited.
Connection configuration, custom install directories and PATH opt-out behavior
are retained. The exact published `0.5.0-rc-1` /
`e359975527bd483707dc39d23599545a77c1620d` pair retains its frozen pre-media
archive contract so the public installers can still install that release.
No other missing/partial media package enters this legacy path, and new package
builds always require the complete toolkit. Video preparation needs writable private temporary storage (up to
1 GiB per invocation); original input mounts can stay read-only.

To update FFmpeg, verify a new official release signature with the reviewed key,
review the source/profile and notices, update the source pin, and rerun source,
archive, all native toolkit/package/installer/Docker gates and the live Echo
matrix. A local pass never substitutes for another platform's native acceptance.

## Published release acceptance

After all release assets are published, run the public acceptance workflow from
main with the exact tag. For the first 0.5 release candidate:

```bash
gh workflow run release-acceptance.yml --ref main -f version=0.5.0-rc-1
```

The workflow runs on Linux amd64/arm64, macOS arm64 and Windows amd64. It checks
that the tag is in main history, anonymously downloads the matching archive and
SHA256SUMS, checks the embedded version/source SHA, and invokes the public main
installer in isolated user directories. Fresh and repeat installs are followed
by CLI/configuration smoke tests using a local HTTP server and synthetic data.
No production API credentials or workflow artifact fallback are used.

Smoke commands follow the verified tag's source, rather than the current checkout:
the earlier 0.5.0-rc-1 has no doctor command. The helper selects the historical
`--without-doctor` smoke mode only when DoctorCommand.php is absent from that
source commit. New package builds retain mandatory doctor help and diagnostics.

To repeat acceptance on one supported native machine, fetch main and tags, then:

```bash
python3 build/release_acceptance.py --version 0.5.0-rc-1 --resolve-source-sha
python3 build/release_acceptance.py --version 0.5.0-rc-1 --source-sha FULL_TAG_COMMIT_SHA --target macos-arm64
```

Use the full SHA printed by the first command and the current machine's target.
On Windows, run the helper with `python`; it invokes native Windows PowerShell 5.1.

## Distribution notices and source provenance

New platform archives omit `BITRIX24-CLI-LICENSE.txt` and `YIIPRESS-LICENSE.txt`:
neither project is an application dependency. The PHAR generator and linkage
checker are build-only, but the PHAR bootstrap and compiled PHP micro patches
are derived from b24cli (whose packaging pipeline derives from YiiPress).
Their applicable copyright, terms and disclaimers are consolidated in the
existing `THIRD-PARTY-NOTICES.md` shipped with every archive. Source provenance
and the original license files remain in `PACKAGING-NOTICES.md` and `build/`.
The Windows UTF-8 patch uses PHP CLI APIs; PHP/runtime notices remain bundled.

`THIRD-PARTY-NOTICES.md` describes the shipped application and runtime. Archives
still include `RARUS-ECHO-LICENSE.txt`, `runtime-licenses/`, `media-licenses/`,
media manifests/checksums and `media-source.tar.xz`. Build-only reports, probes,
local state and build-helper license files stay outside new archives.

Both installers accept the two historical license files when present and replace
the active notices directory completely on update. Archive validation accepts
the old required files for published RC-2 only at tag `0.5.0-rc-2`, source
`ba667107bb7611cbc0f3f47859cb63e970a635b9`. The frozen RC-1 no-media contract
retains its original required files and source pin. Other source identities must
follow the reduced archive composition. Published assets are not modified.
