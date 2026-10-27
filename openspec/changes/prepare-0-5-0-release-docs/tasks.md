# Tasks

## 1. Documentation

- [x] 1.1 Audit merged commits since 0.4.0 and summarize the cumulative release scope in CHANGELOG Unreleased; verify rc-1 history is unchanged and #60 remains pending.
- [x] 1.2 Promote standalone installation in README, remove Docker history/walkthrough, and use local CLI examples; verify installer options and availability against install.sh/install.ps1 and current published tags.
- [x] 1.3 Preserve SDK Composer setup and command/config reference; verify doctor availability and avoid a stable release date or unavailable stable constraint.

## 2. Integration checks

- [x] 2.1 Run make lint-openspec and git diff --check; require success.
- [x] 2.2 Run make test-unit and make lint-all; record outcomes or exact infrastructure blockers.
- [ ] 2.3 Open a documentation PR into dev linked to #68; inspect CI and agent reviews, documenting any pending checks.

## Workflow follow-up

- Reconcile README/CHANGELOG with PR #67 after merge, before fixing the rc-2 release scope.
- Download public rc-2 assets for macOS arm64 after its tag build succeeds; verify checksum and source identity and provide local installation commands to the user.
- Record user manual acceptance before preparing final stable rollout/date/constraint.
- Archive this documentation change only after its PR merges. Track final release completion in issue #68.

## Validation evidence

- `make lint-openspec`: 23 items passed.
- `git diff --check`: passed.
- `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk make test-unit`: 305 tests / 886 assertions passed.
- `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk make lint-all`: passed (OpenSpec, plugin reference validation, PHP CS Fixer, PHPStan and Rector).
- A new Compose project initially hit the Docker address-pool limit. Checks reused the existing project network; no networks or unrelated containers were removed.
