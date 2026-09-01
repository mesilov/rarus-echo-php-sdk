## Context

`submit`, `status`, and `transcript` are already separate CLI commands backed by `EchoClientInterface`. That low-level surface must remain available, but common shell and Docker automation needs a higher-level path that submits files and waits for final transcript payloads without user-side glue code.

## Goals

- Provide `rarus-echo submit <file>... --wait` as a backward-compatible extension of the existing command.
- Preserve the existing `submit` output when `--wait` is not passed.
- Keep machine-readable stdout clean: JSON stdout contains only the final payload, raw stdout contains only transcript text, and progress goes to stderr.
- Support multiple submitted files for JSON/human-readable wait output.
- Restrict `--raw-result` and `--output` to single-file submissions.

## Non-Goals

- Do not add a live integration test that depends on account balance or queue timing.
- Do not add `--output-dir` for multiple files in this change.
- Do not change public SDK service interfaces beyond using existing `EchoClientInterface::getTranscript()`.

## Approach

Add a small `TranscriptPoller` helper in `Rarus\Echo\Infrastructure\Console` that accepts an `EchoClientInterface`, submitted `Uuid` values, polling settings, and callbacks for progress/sleep behavior. The helper repeatedly calls `getTranscript()` for non-terminal file IDs, tracks the latest known result per file, and returns all successful final results.

`SubmitCommand` will own CLI concerns:

- parse and validate wait/output options;
- submit files with the existing transcription options builder;
- write `submitted`, `polling`, and `completed` progress lines to stderr while waiting;
- render final wait results as JSON, human-readable text, raw transcript text, or a single output file.

The poller will use `sleep()` by default through an injectable callable. Tests will pass a no-op sleeper and a deterministic clock/elapsed-time callback so timeout behavior is covered without slowing the suite.

## Error Handling

- `--raw-result` without `--wait` fails early because no transcript result is available from a plain submit.
- `--output` without `--wait` fails early for the same reason.
- `--raw-result` with multiple files fails before client creation.
- `--output` with multiple files fails before client creation.
- Non-positive `--poll-interval` or `--timeout` fails before client creation.
- Timeout returns non-zero and writes last known file states to stderr.
- A terminal non-success transcript result returns non-zero and identifies the file ID and status.
- SDK exceptions keep the existing `Error: ...` stderr behavior and non-zero exit code.

## Testing

Unit tests will drive the implementation first:

- `submit --wait --json` polls waiting, processing, and success responses and keeps progress on stderr.
- `submit --wait --raw-result` emits only the transcript body using raw output.
- `submit --wait --output=<path>` writes the transcript to the given file and leaves stdout empty except progress on stderr.
- invalid combinations and invalid numeric options fail before creating the client.
- repeated non-terminal statuses time out without sleeping in tests.
- terminal failure status fails explicitly.
- service exceptions are still written through the common CLI error path.
- command help lists the new wait options.
