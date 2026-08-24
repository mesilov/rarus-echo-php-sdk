## Why

SDK users need a quick way to operate the RARUS Echo service from a terminal without writing throwaway PHP scripts for common actions. Issue #11 requests a CLI app and references the Command Line Interface Guidelines, so the SDK should expose a small, discoverable, script-friendly command line entrypoint.

## What Changes

- Add a Composer-installed `rarus-echo` CLI entrypoint.
- Add CLI commands for common service workflows: submit files for transcription, read a file status, fetch a transcript, and inspect queue information.
- Read credentials from the existing `RARUS_ECHO_API_KEY`, `RARUS_ECHO_USER_ID`, and optional `RARUS_ECHO_BASE_URL` environment contract, including local `.env` files when available.
- Support human-readable output by default and JSON output for automation.
- Add CLI help, validation, exit codes, stdout/stderr behavior, unit tests, and README usage examples.
- Add the Symfony Console component as a runtime dependency with a CI-compatible initial version range.
- Update GitHub Actions Composer cache keys so dependency metadata changes invalidate the restored `vendor` directory.

## Capabilities

### New Capabilities

- `cli-app`: Command-line application for common RARUS Echo service operations.
- `ci-composer-cache`: GitHub Actions Composer dependency cache invalidates when Composer dependency metadata changes.

### Modified Capabilities

- None.

## Impact

- Affected runtime code: new CLI namespace under `src/Cli/` and a new `bin/rarus-echo` executable.
- Affected package metadata: `composer.json` gains a `bin` entry and the Symfony Console dependency.
- Affected documentation: `README.md` gains CLI installation and usage examples.
- Affected tests: new unit tests for command behavior, output formatting, option parsing, and error handling.
- Affected CI: Composer dependency cache keys in GitHub Actions lint and unit-test workflows.
- Public SDK service APIs remain unchanged; the CLI uses existing `ServiceFactory`, credentials, queue, status, and transcription services.
