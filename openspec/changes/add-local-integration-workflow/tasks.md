## 1. Workflow Implementation

- [x] 1.1 Add focused Make targets for core, queue, status, and transcription integration suites.
- [x] 1.2 Centralize integration-test credential and audio fixture guards.
- [x] 1.3 Remove debug output and incorrect namespaces from integration tests.
- [x] 1.4 Document `.env.local` setup and local integration commands in README.

## 2. Verification

- [x] 2.1 Run `make test-integration-core`.
- [x] 2.2 Run `make test-integration`.
- [x] 2.3 Run repository validation with `make lint-openspec`, `git diff --check`, `make test-unit`, and `make lint-all`.
