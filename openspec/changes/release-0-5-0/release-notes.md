# RARUS Echo 0.5.0

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

## Installation

Download the complete archive for your platform and verify it against SHA256SUMS. Keep the media tools, metadata and licenses with the executable. Linux/macOS can also use the public install.sh with --version 0.5.0; Windows uses install.ps1 -Version 0.5.0. Composer: `composer require mesilov/rarus-echo-php-sdk:^0.5`.
