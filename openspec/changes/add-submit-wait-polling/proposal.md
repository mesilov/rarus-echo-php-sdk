## Why

The CLI currently submits files and returns `file_ids`, but callers that need completed transcripts must write their own polling loop around `transcript` or `status`. Issue #24 asks for a first-class, automation-safe `submit --wait` path so Docker and shell users can upload audio and receive the final transcript or final JSON from one command.

## What Changes

- Add `submit --wait` to poll every submitted file until each transcript reaches a terminal state or the timeout expires.
- Add `--poll-interval=<seconds>` and `--timeout=<seconds>` with conservative defaults.
- Add `--raw-result` for single-file transcript-only stdout and `--output=<path>` for single-file transcript output to disk.
- Keep progress and diagnostics on stderr while preserving deterministic stdout for JSON, raw transcript text, and shell redirection.
- Handle interrupt and terminate signals during long-running waits by writing a shutdown message to stderr and returning a signal-aware exit code.
- Validate incompatible option combinations before creating the SDK client or submitting files.
- Document local and Docker CLI usage for the one-shot audio-to-transcript workflow.
- Add unit coverage for success, progress separation, invalid options, timeout, service exceptions, terminal failures, signals, raw output, output files, and help text.

## Capabilities

### New Capabilities

- `cli-submit-wait-polling`: CLI `submit` can optionally wait for terminal transcript results with stable stdout/stderr and exit-code behavior.

### Modified Capabilities

- None.

## Impact

- Affected runtime code: `src/Infrastructure/Console/Command/SubmitCommand.php` and a focused CLI polling helper under `src/Infrastructure/Console/`.
- Affected tests: `tests/Unit/Infrastructure/Console/Command/SubmitCommandTest.php` and fake CLI client support.
- Affected docs: `README.md` CLI examples and option list.
- Public PHP SDK service APIs and existing non-wait CLI behavior remain backward-compatible.
