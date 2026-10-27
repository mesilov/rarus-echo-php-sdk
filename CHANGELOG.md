# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
