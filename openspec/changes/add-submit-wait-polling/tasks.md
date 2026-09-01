## 1. OpenSpec

- [x] 1.1 Create proposal, design, spec delta, and task list for issue #24.
- [x] 1.2 Validate OpenSpec with `make lint-openspec`.

## 2. CLI polling tests

- [x] 2.1 Add failing unit coverage for `submit --wait --json` success with progress on stderr and no progress on stdout.
- [x] 2.2 Add failing unit coverage for `submit --wait --raw-result` single-file transcript-only stdout with raw output.
- [x] 2.3 Add failing unit coverage for `submit --wait --output=<path>` writing transcript text to disk.
- [x] 2.4 Add failing unit coverage for invalid wait option combinations before client creation.
- [x] 2.5 Add failing unit coverage for timeout, terminal failure, and SDK exception behavior.
- [x] 2.6 Add failing unit coverage for submit help documenting wait options.

## 3. CLI implementation

- [x] 3.1 Add focused polling support under `src/Infrastructure/Console/` using existing `EchoClientInterface::getTranscript()`.
- [x] 3.2 Wire `SubmitCommand` options, validation, stderr progress, and final wait output modes.
- [x] 3.3 Preserve existing non-wait `submit` behavior.
- [x] 3.4 Extend fake CLI client support for deterministic transcript polling tests.

## 4. Documentation

- [x] 4.1 Update README CLI examples for `submit --wait --json`, Docker one-shot usage, raw transcript redirection, timeout, and polling interval.
- [x] 4.2 Update README option list to include the new wait options and stdout/stderr contract.

## 5. Verification

- [x] 5.1 Run focused submit command unit tests.
- [x] 5.2 Run `make lint-openspec`.
- [x] 5.3 Run `git diff --check`.
- [x] 5.4 Run `make test-unit`.
- [x] 5.5 Run `make lint-all`.
