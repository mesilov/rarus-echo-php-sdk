# Tasks

## 1. Configuration and report contract

- [x] 1.1 Add immutable diagnostic results and source-aware configuration resolution; verify config/source/validation tests and document precedence in the design.
- [x] 1.2 Add configuration diagnostics for missing, invalid, unsafe and overridden files; verify deterministic config tests without credentials in reports.

## 2. External checks

- [x] 2.1 Add bounded authenticated queue connectivity diagnostics with categorized errors; verify protocol fixture tests and document the ten-second deadline.
- [x] 2.2 Add Symfony Process dependency and ffmpeg path/version checks; verify controlled executable tests including failure and timeout and document the five-second deadline.

## 3. Command and documentation

- [x] 3.1 Register doctor with independent checks, text/JSON output and exit codes; verify command/help tests and update README, generated CLI reference and CHANGELOG.

## 4. Integration verification

- [x] 4.1 Run make test-unit, make lint-all and git diff --check; verify CLI smoke reports and include doctor in native archive acceptance.
- [ ] 4.2 Open PR targeting dev with Closes #61; verify required CI and process agent review comments.

## Workflow follow-up

- Archive add-cli-doctor only after the linked PR merges.
