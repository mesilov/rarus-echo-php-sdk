## Context

Issue #12 asks to update documentation and check examples for the 0.3.0 release. The repository does not have an `examples/` directory, and examples should stay in README so users see Docker CLI and PHP SDK usage without jumping across files.

## Decision

Keep examples inline in README:

- Docker CLI happy-path commands remain first in quick start.
- PHP SDK examples cover credentials, submit, status, transcript retrieval, and queue/status-list usage.
- Development docs include focused integration-test commands and local credentials.

Update CONTRIBUTING so it no longer directs contributors to add files under `examples/`. Documentation examples should be added to README or PHPDoc where useful.

Mark 0.3.0 as released in CHANGELOG with the current release date and remove stale 0.1.0 upcoming notes that no longer match the repository history.

## Validation

Documentation snippets are checked with syntax-focused PHP validation where possible, and repository quality gates remain the final proof:

- `make lint-openspec`
- `git diff --check`
- `make test-unit`
- `make lint-all`
