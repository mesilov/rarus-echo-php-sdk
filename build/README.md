# Standalone CLI packaging

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/53

The build uses the production lock at docker/rarus-echo-cli/composer.lock.
build/config.json pins PHP 8.5.11, static-php-cli and PHP micro commits, extensions,
and reviewed patches. build-info.json records the version, full source SHA,
lock SHA256, runtime pins and patch SHA256 values. runtime-sources.json records
SPC's downloaded source lock. Compiler caches live under ignored runtime/. Hashed inputs are checked out with LF
endings on every platform; a regression test covers core.autocrlf=true.

The starting point is mesilov/bitrix24-cli at
f23ca85fe45b003cedd0b6fd0340da6ac850ba00. See THIRD-PARTY-NOTICES.md and
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
application/packaging licenses and runtime-licenses/. Unix mode is 0755.
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
