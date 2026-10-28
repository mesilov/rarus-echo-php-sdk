Third release candidate for **0.5.0**, assembled from `dev` for testing before stable publication. Latest stable remains **0.4.0**.

## Included in the 0.5.0 line

- Standalone CLI with embedded PHP for Linux amd64/arm64, macOS arm64 and Windows amd64; PHP, Composer and Docker are not needed.
- One-line installers, private saved connection configuration via `rarus-echo install`, and global `--config PATH`.
- `rarus-echo doctor` for configuration, authenticated Echo access and bundled media-tool diagnostics.
- Bundled compact FFmpeg/ffprobe and automatic audio preparation when submitting video, with stream selection and bounded cleanup.

## New since RC-2

- `rarus-echo extract-audio recording.mp4` saves verified audio beside the video without uploading or configuring Echo. Supports stream selection, timeout and JSON; existing output files are never replaced (#78).
- Standalone executables compressed with UPX on Linux/Windows and wrappe on macOS, reducing executable size by about 68% in native acceptance, with provenance and original/packed behavioral checks (#74).
- Cleaner archives: applicable build-helper terms are consolidated in THIRD-PARTY-NOTICES.md; runtime/media licenses and source materials remain included (#73).
- Short README with a command index and detailed linked installation, CLI, SDK and agent-plugin guides (#72).
- Transcription skill uses the installed CLI, offers installation when missing and checks the available command contract (#79).
- Windows installer retries brief executable sharing/lock violations within a fixed budget; permissions and persistent failures retain rollback behavior (#81).
- Fixed extract-audio's global --config entry in the generated CLI reference (#82).

## Install this prerelease

Linux amd64/arm64 or macOS Apple Silicon:

```sh
curl -fsSL --proto '=https' --proto-redir '=https' https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.sh | sh -s -- --version 0.5.0-rc-3
```

Windows amd64, PowerShell:

```powershell
& ([scriptblock]::Create((Invoke-WebRequest -UseBasicParsing https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.ps1).Content)) -Version 0.5.0-rc-3
```

Download the complete platform archive and matching SHA256SUMS from this release. Keep the media toolkit and licenses beside the executable. macOS uses an executable temporary extraction cache. This candidate does not replace the separate manual acceptance and stable-release work in #68; README Latest links await stable assets (#72).
