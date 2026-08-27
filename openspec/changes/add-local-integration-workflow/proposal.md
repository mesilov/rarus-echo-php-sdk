## Why

Issue #2 leaves local integration tests and quick-start documentation incomplete. Maintainers need predictable commands and credential handling for live Echo API checks without leaking secrets or relying on ad hoc test classes.

## What Changes

- Add documented Make targets for running all integration tests or focused core, queue, status, and transcription suites.
- Centralize integration-test credential and audio fixture guards.
- Document `.env.local` setup and Docker CLI happy-path usage in the README.

## Capabilities

### New Capabilities
- `local-integration-workflow`: Maintainer workflow for local live Echo API integration tests.

### Modified Capabilities

## Impact

- Affected files: `Makefile`, `README.md`, and `tests/Integration/**`.
- Local process impact: maintainers can run focused integration suites with credentials from ignored local environment files.
- Runtime SDK API impact: none.
