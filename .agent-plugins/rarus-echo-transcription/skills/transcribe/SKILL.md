---
name: transcribe
description: Use when submitting audio or video to RARUS Echo, inspecting its queue, checking transcription status, or fetching transcripts through the installed rarus-echo CLI.
---

# RARUS Echo Transcription

Perform every Echo operation through the installed `rarus-echo` CLI. Never call
Echo HTTP endpoints directly, invoke PHP SDK code, or write a replacement client
in Python, PHP, shell, or another language. Do not fall back to Docker, Composer,
`php bin/rarus-echo`, or external transcription services when the executable is
missing, outdated, or fails. File handling and parsing CLI output are allowed.

## CLI prerequisite

Before the first Echo operation in a session:

1. Locate `rarus-echo` with `command -v rarus-echo` (POSIX) or
   `Get-Command rarus-echo -CommandType Application` (PowerShell). Honor an explicit
   executable path supplied by the user. Windows uses `rarus-echo.exe`.
2. Run that executable with `--version`. If absent or unable to start, explain the
   problem, **offer installation**, and read [installation.md](references/installation.md).
   Do not install automatically; continue with installation if already authorized.
   Otherwise wait for the user's choice before dependent work.
3. Read [cli.md](references/cli.md), the generated **0.5.0 call map**, before choosing
   commands/options. Its verified baseline is **0.5.0-rc-2**, a prerelease.
   Check installed `help <command> --format=json` for the requested workflow;
   installed help takes precedence over this map. If a required command/option is
   absent, offer an upgrade. Do not invent flags or implement a workaround.

Installing the program and `rarus-echo install` are different operations: `install`
configures a connection interactively. If connection settings are missing, direct
user entry to that command in a terminal; never ask for API keys in chat.
`doctor --json` checks configuration, authenticated access and media tools; it
contacts Echo, so use it for requested diagnostics or connection setup, not as a
mandatory preflight for every transcription.

## Inputs and command map

Accept requests such as `transcribe recording.mp4 --language=ru`, `queue`,
`status <file-id>` or `transcript <file-id>`. Ask one concise question if the
required local file path or file UUID is missing. Quote paths and use `--` before
file arguments. Use the same selected executable throughout the workflow.

| Intent | CLI call |
| --- | --- |
| Identify version | `rarus-echo --version` |
| Inspect command contract | `rarus-echo help submit --format=json` |
| Configure connection (interactive) | `rarus-echo install` |
| Diagnose connection/media | `rarus-echo doctor --json` |
| Inspect queue | `rarus-echo queue --json` |
| Submit files | `rarus-echo submit --language=ru --json -- "/absolute/path/audio.ogg"` |
| Submit and wait | `rarus-echo submit --language=ru --wait --json -- "/absolute/path/video.mp4"` |
| Check status | `rarus-echo status <file-id> --json` |
| Fetch transcript | `rarus-echo transcript <file-id> --json` |

Use `--config "/path/connection.json"` to select an existing private connection
file. Existing environment/dotenv credentials are also supported; see
[installation.md](references/installation.md) for precedence. Never print API keys,
user IDs, config/env-file contents, or commands containing literal credentials.
Do not submit files unless the user requested submission and credentials exist.

## Results and media

- Prefer `--json`; parse stdout separately from stderr progress. Queue fields are
  `files_count`, `files_size`, `files_duration`. Non-wait submission returns
  JSON `file_ids`. With `--wait`, retain `submitted: <UUID>` lines from stderr
  before polling: a timeout may produce no JSON result. Preserve input order.
  After partial failure or a wait timeout,
  keep reported IDs and resume status/transcript lookup; do not blindly resubmit.
- Prefer CLI `submit --wait` when the user requests the finished transcript.
  `--poll-interval` defaults to 30 seconds and `--timeout` to 7200 seconds.
  `--raw-result` and `--output` require `--wait` and exactly one input file.
  Use them for a requested text artifact; do not mix raw stdout with JSON parsing.
  Successful `--output` writes the file and returns without JSON stdout, even
  with `--json`; verify the requested artifact after a successful exit.
- Report `waiting` and `processing` as in progress; `success` is success and `failure`
  is terminal failure. Preserve the CLI status and error details without secrets.
- Pass local video directly to `submit`. The complete standalone package includes
  FFmpeg/ffprobe: keep `libexec` and media metadata. Do not run a separate converter
  or install host FFmpeg as a workaround. `--audio-stream` selects an absolute
  audio stream index for one video; `--media-timeout` defaults to 600 seconds per
  input and is independent of polling. `--no-extract-audio` conflicts with
  `--audio-stream`; use it only when original-file upload was explicitly requested.

## Maintenance

Plugin packaging: [distribution.md](references/distribution.md). From the repository
root, regenerate the map after CLI changes with
`.agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh`, then run
`make lint-agent-plugins`. Update the target/baseline when preparing a new release.
