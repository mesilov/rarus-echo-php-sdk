# Tasks

## 1. Paths and protected storage

- [x] 1.1 Implement OS/explicit path resolution and validate with unit tests including absent user directories, spaces and Unicode.
- [x] 1.2 Implement validated private atomic JSON storage; verify malformed data, permissions, symlink rejection and preservation on failed writes.

## 2. CLI setup and source selection

- [x] 2.1 Add global --config, configurable factory wiring and deterministic source precedence; verify explicit isolation and legacy env/dotenv compatibility.
- [x] 2.2 Add interactive install with hidden input and overwrite confirmation; verify dialogue, cancellation, validation and noninteractive behavior with tests.

## 3. Documentation and native acceptance

- [x] 3.1 Update README, generated transcription CLI reference and CHANGELOG; verify make lint-agent-plugins.
- [x] 3.2 Extend native acceptance for config setup/read/override on all four platforms and verify private Windows ACLs in CI.

## 4. Validation and delivery

- [x] 4.1 Run focused/full PHPUnit, make lint-all, make lint-openspec and git diff --check; record evidence.
- [x] 4.2 Push branch and open PR into dev with Closes #55, verify required CI and address agent review comments.

## Workflow follow-up

- Archive this change and remove its worktree only after the PR merges.

## Verification evidence

- `make test-unit`: 246 tests, 626 assertions.
- `make lint-all`, `make lint-openspec`, `git diff --check` and 7 packaging contract tests passed.
- PR #56 targets dev and closes #55. All four native targets and the combined release-archive check passed at 8d2c588 in [Standalone CLI run 40491283856](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40491283856).
- macOS standalone was also tested locally with a real PTY: hidden API-key input, private Unicode config, queue from another cwd and explicit-file precedence against invalid ambient credentials.
- GitHub Codex review completed without findings; internal review findings about concurrent first writers and directory creation were fixed and regression-tested. No unresolved review threads remained at delivery.
