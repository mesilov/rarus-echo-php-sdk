# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-10-28

Standalone CLI release for Linux amd64/arm64, macOS arm64 and Windows amd64. This stable release combines the changes delivered in RC-1, RC-2 and RC-3. PHP SDK integrations remain supported through Composer.

### Added
- Standalone CLI packages with embedded PHP, build metadata, version/source SHA, runtime licenses and SHA256SUMS; PHP, Composer and Docker are not required to run the native CLI (#53).
- One-line POSIX and PowerShell installers with SHA-256 and identity checks, user-scoped installation, repeat installation, PATH activation and retained licenses (#57).
- Interactive `rarus-echo install`, private per-user connection configuration and global `--config PATH`, with explicit-file isolation and existing env/dotenv compatibility (#55).
- `rarus-echo doctor` diagnoses configuration and its selected source, authenticated Echo access and media-tool availability. Text/JSON reports provide independent bounded checks, actionable errors and script-friendly exit codes (#61).
- Bundled compact FFmpeg/ffprobe with pinned source, provenance, licenses and corresponding source materials for standalone and Docker distributions (#60).
- Automatic audio preparation for local video submitted through CLI: deterministic stream selection, verified copy/lossless formats, timeline preservation, bounded cleanup and optional `--audio-stream`, `--no-extract-audio` and `--media-timeout` controls (#60).
- Local `extract-audio <file>` saves verified audio beside the video without credentials or upload, supporting stream selection, timeout and JSON output without replacing an existing destination (#78).
- Four-platform native packaging and public-release acceptance verify archive/source identity, fresh/repeat installation, configuration/PATH preservation and CLI/media behavior (#59).

### Changed
- Standalone `--version` reports embedded version/source identity; Composer CLI reports the installed package version (#53).
- Compress standalone executables with pinned UPX on Linux/Windows and wrappe on macOS, reducing executable size by about 68% in native acceptance. Preserve compression provenance and original/packed behavioral checks; macOS uses an executable temporary extraction cache (#74).
- Consolidate applicable b24cli/YiiPress build-helper terms into `THIRD-PARTY-NOTICES.md` instead of shipping separate helper license files. Keep runtime/media licenses and historical archive installation compatibility (#73).
- Shorten README around native downloads, curl-only installation, first use and a command index; move full installation, connection, CLI, PHP SDK, agent-plugin and development guides into `docs/` (#72).
- Use the installed CLI in the transcription skill, offer installation when missing and maintain a release-aware seven-command map with connection/media guidance (#79).
- Keep hand-written `docs/cli.md` and the generated skill reference synchronized, retain Composer examples in `docs/install/php.md` and require non-interactive `gh` for GitHub maintenance (#68, #78).

### Fixed
- Preserve expandable Windows user PATH entries and their registry value type during installation and rollback (#59).
- Support Unicode Windows temporary paths without the PowerShell 5.1 CodeDOM compiler (#59).
- Retry transient Windows executable sharing/lock violations for at most twenty attempts separated by 250 ms, preserving permission failures and rollback for persistent errors (#78, #81).
- Submit audio-only WebM unchanged without requiring media tools when fileinfo reports the shared video/webm MIME; actual video retains verified preparation (#60, #69).
- Select historical release smoke commands from the verified tagged source while retaining doctor and media checks for newer builds (#59).
- Disable automatic Git maintenance in temporary packaging-test repositories to prevent cleanup races (#55).
- Include global `--config` in the extract-audio reference allowlist so strict reference validation accepts local extraction (#78, #82).

## [0.5.0-rc-3] - 2026-10-28

Third release candidate for 0.5.0, prepared from dev. It retains standalone CLI packages for four platforms, one-line installers, saved connection configuration, doctor and bundled FFmpeg/ffprobe from earlier candidates. This candidate adds local audio extraction, smaller executables and updated documentation. Prerelease publication preserves Latest stable 0.4.0.

### Added
- Local `extract-audio <file>` command saves verified audio beside its video with the same basename, without credentials or upload. Supports stream selection, media timeout, JSON output and safe no-overwrite publication (#78).

### Changed
- Compress standalone CLI executables using pinned UPX on Linux/Windows and wrappe on macOS, reducing executable size by about 68% in native acceptance. Packages retain compression provenance, size/hash reports, licenses and original/packed behavioral checks. macOS uses an executable temporary extraction cache; bundled media stays beside the portable or installed launcher (#74).
- Shorten README around Latest binary downloads, curl-only installation, first use and a compact command index. Move detailed installation, connection, CLI, SDK, agent plugin and development instructions into linked `docs/` guides. Latest binary availability remains gated by stable release #68 (#72).
- Omit separate b24cli and YiiPress build-helper license files from new standalone archives, consolidating applicable upstream terms in `THIRD-PARTY-NOTICES.md`. Runtime/media licenses remain bundled and installers retain compatibility with published historical archives (#73).
- Require the installed CLI in the transcription skill, offer standalone installation when unavailable, and maintain a release-aware 0.5.0 command map with connection setup and media guidance (#79).
- Synchronize the hand-written `docs/cli.md` with the generated transcription-skill reference in maintainer/contributor workflows. Keep pinned Composer examples in `docs/install/php.md` and require non-interactive `gh` for GitHub maintenance (#68, #78).

### Fixed
- Include the accepted global `--config` option in the `extract-audio` reference allowlist so the stricter reference validator accepts local extraction (#78, #82).
- Tolerate brief Windows executable sharing/lock violations during atomic installation with at most twenty attempts separated by 250 ms. Permission errors and persistent locks retain existing failure and rollback behavior (#78, #81).

## [0.5.0-rc-2] - 2026-10-28

Second release candidate for 0.5.0, adding automatic video audio preparation, bundled compact FFmpeg/ffprobe and doctor. Native packages are built from the tagged main commit; prerelease publication preserves Latest stable.

### Added
- Compact source-pinned FFmpeg/ffprobe toolkit for Docker and standalone CLI packages, with offline provenance/source materials and coordinated installer updates (#60).
- Automatic CLI audio preparation for local video, deterministic stream selection, verified copy/lossless output routes, timeline preservation and bounded cleanup; optional `--audio-stream`, `--no-extract-audio` and `--media-timeout` controls (#60).
- CLI `doctor` diagnoses saved config structure/protection and selected connection source, authenticated Echo access with a ten-second deadline, and ffmpeg availability/version with a five-second deadline; includes text/JSON reports, independent checks, actionable errors and script-friendly exit codes (#61).

### Fixed
- Preserve toolkit-free submission of audio-only WebM when fileinfo reports the shared video/webm MIME; bounded container inspection keeps actual video on the preparation path (#60, #69).
- Bootstrap the Windows installer with Unicode temporary paths without invoking the PowerShell 5.1 CodeDOM compiler (#59).
- Select historical release smoke commands from the verified tagged source, keeping doctor mandatory for new builds while accepting the earlier RC (#59).

## [0.5.0-rc-1] - 2026-10-27

First release candidate for 0.5.0. Standalone archives are built from the tagged main commit; prerelease publication does not replace Latest stable.

### Fixed
- Preserve raw expandable Windows user PATH entries and registry value type when installing or rolling back the CLI (#59).
- Disable automatic Git maintenance in temporary packaging-test repositories to prevent cleanup races on CI runners (#55).

### Added
- Native acceptance of published release assets through the public main installers, with checksum/source identity verification, repeat installation and CLI smoke tests on all four supported platforms (#59).
- One-line standalone CLI installers for Linux amd64/arm64, macOS arm64 and Windows amd64, with public-release selection, SHA-256 verification, user-scoped installation, retained licenses and optional PATH activation (#57). Public use requires scripts on main and a Release containing standalone assets.
- Interactive CLI `install`, private per-user connection configuration and global `--config PATH`, with explicit-file isolation and legacy env/dotenv compatibility (#55).
- Standalone CLI packaging for Linux amd64/arm64, macOS arm64, and Windows amd64, with embedded PHP, version/source SHA, build metadata, runtime licenses, final archive SHA256SUMS, native acceptance tests, and gated GitHub Release publication on version tags (#53).

### Changed
- CLI `--version` now reports embedded build identity for standalone packages and the installed Composer package version otherwise.

## [0.4.0] - 2026-09-03

### Added
- Complete CLI key reference in the README `## CLI` section ("Справочник команд и опций"): global options (with `--json` scoped to the Echo commands and `--silent` noted as requiring Symfony Console ≥ 7.2) plus per-command arguments and options for `queue`, `submit`, `status`, and `transcript`, with value requirements and defaults.
- Parallel per-issue worktree tooling (`make worktree-new`, `worktree-remove`, `worktree-list` backed by a maintainer-skill script `.agents/skills/rarus-echo-maintainer/scripts/worktree.sh`): creates a git-ignored `.worktree/<issue>-<slug>` checkout branched off `origin/<base>`, provisioned with a symlinked `.env.local` and an independent clone-copied `vendor/`, and removes it while keeping the branch.
- Cross-agent `rarus-echo-transcription` plugin with a shared transcription skill, marketplace entries, and CLI reference drift validation for agent sessions.
- CLI `submit --wait` can now submit audio and poll until terminal transcript results, including JSON, raw transcript, and output-file modes while keeping progress on stderr.
- Long-running `submit --wait` commands now handle `SIGINT` and `SIGTERM` gracefully by writing a shutdown message to stderr and exiting with a signal-aware non-zero status.
- GitHub issue templates for bug reports and release rollout requests.
- Configurable HTTP client idle timeout: `ApiClientFactory::withHttpTimeout()` plus a `RARUS_ECHO_HTTP_TIMEOUT` environment variable (positive integer seconds) override the default when the HTTP client is auto-discovered.

### Fixed
- Large file uploads no longer abort with `Idle timeout reached for "…"` from the auto-discovered Symfony HttpClient: the SDK now builds the default HTTP client with an explicit 600s idle timeout instead of PHP's ~60s `default_socket_timeout`, so submitting large audio/video files completes (#43).
- Corrected the Claude Code plugin install instructions in `README.md` and the transcription skill's `distribution.md`: the repo-local marketplace source must be `./` (the CLI rejects a bare `.` with `Invalid marketplace source format`), so `claude plugin marketplace add .` is now `claude plugin marketplace add ./`.

### Changed
- Maintainer workflow now requires aligning CLI documentation in both places on any CLI change: the generated transcription-skill CLI reference (`update-cli-reference.sh`, drift-checked by `make lint-agent-plugins`) and the README `## CLI` section.
- CLI reference generator (`update-cli-reference.sh`) single-sources the command list from the shell `PROJECT_COMMANDS` array (the Node generator receives it as arguments, with no duplicate hardcoded list) and fails with a clear, actionable message when a command has no `optionAllowlist` entry instead of crashing.
- CLI Docker image now builds on the official `php:8.4-cli-alpine` runtime base instead of `php:8.4-cli-bookworm`, reducing published image size while preserving `rarus-echo` behavior, the `curl`/`fileinfo`/`mbstring` extensions, PSR-17/PSR-18 discovery smoke checks, and multi-arch `linux/amd64`/`linux/arm64` publication.
- Renamed the local development/CI container from `php-cli` to `dev-php` (directory `docker/dev-php/`, Compose service `dev-php`) so its name reflects its purpose and is distinct from the published `rarus-echo-cli` image; the shell helpers are now `make dev-php-bash` and `make dev-php-root`.
- README now displays CI status badges for the Lint and Tests GitHub Actions workflows.
- README Docker CLI examples no longer use `--pull=always` by default; the pull flag remains documented as an opt-in image refresh.
- Maintainer workflow now creates issue worktrees with `make worktree-new`, branching directly off `origin/<base>` instead of first updating local `dev`.
- Maintainer workflow now documents release changelog rollover and README installation example updates.
- Distributed Composer package is now lean: `.gitattributes` `export-ignore` excludes development, CI, tooling, docs-process, and agent/maintainer files, leaving consumers only the runtime SDK.
- `rarus-echo-transcription` transcription skill now makes the published Docker CLI the unconditional default execution path; agents run it directly instead of probing for host PHP or a local binary, and local `vendor/bin/rarus-echo` / `php bin/rarus-echo` execution is documented as an explicit opt-in for when the user asks or Docker is unavailable.

## [0.3.0] - 2026-08-27

### Added
- CLI app `vendor/bin/rarus-echo` for queue, status, transcript, and submit service operations.
- Docker image build pipeline for the CLI app with GHCR publication.
- Local integration test targets for core, queue, status, and transcription services.
- OpenSpec-backed maintainer workflow and repo-local agent skill documentation.

### Changed
- Docker CLI image usage is now the first quick-start path in the README.
- README now keeps release examples inline instead of referencing a separate examples directory.
- Contributor documentation now matches PHP 8.4/8.5 requirements and current SDK namespaces.

### Fixed
- CLI Docker image now includes production PSR-17 discovery dependencies and a 4G PHP memory limit for large audio submissions.

## [0.2.0] - 2026-08-24

### Added
- PHP 8.4 support.
- Initial SDK implementation for asynchronous Echo transcription operations.
- PSR-compatible HTTP client, request factory, stream factory, and logger integration.
- Service layer for transcription, status, and queue operations.
- Unit and integration test coverage for SDK services.

## [0.1.0] - 2026-08-22

### Added
- Initial package scaffold.
