## 1. OpenSpec

- [x] 1.1 Create proposal, design, and spec artifacts for issue #22.
- [x] 1.2 Validate OpenSpec artifacts with `make lint-openspec`.

## 2. SDK Option

- [x] 2.1 Add failing unit tests for default and enabled `timestamps-extended` headers.
- [x] 2.2 Implement `timestampsExtended` on `TranscriptionOptions` and `TranscriptionOptionsBuilder`.
- [x] 2.3 Run focused transcription option unit tests.

## 3. CLI Option

- [x] 3.1 Add failing unit tests for `submit --timestamps-extended` parsing and help output.
- [x] 3.2 Implement the `submit --timestamps-extended` flag.
- [x] 3.3 Run focused submit command unit tests.
- [x] 3.4 Add a live integration path for diarization with extended timestamps, skipped when the API reports insufficient funds.

## 4. Documentation

- [x] 4.1 Update README SDK and CLI examples for diarization with extended timestamps.

## 5. Validation and Delivery

- [x] 5.1 Run `git diff --check`.
- [x] 5.2 Run `make test-unit`.
- [x] 5.3 Run `make lint-all`.
- [x] 5.4 Commit and push `feature/22-diarization-extended-timestamps`.
- [x] 5.5 Open a pull request against `dev` with `Closes #22`.
- [x] 5.6 Check required CI and agent review comments.
