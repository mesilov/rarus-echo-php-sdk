# Tasks

## 1. Persistent audio extraction

- [x] 1.1 Add failing AudioExtractor tests for sibling names, literal Unicode paths, symlink aliases, existing/racing destinations, audio-only rejection, copy failure and cancellation; run the focused test before implementation.
- [x] 1.2 Implement AudioExtractor using existing media preparation and atomic sibling publication; verify focused tests pass and owned files are cleaned.
- [x] 1.3 Add and run opt-in real-tool corpus tests for AAC/Opus copy, FLAC fallback, stream selection and invalid media; document inherited limits in docs/cli.md.

## 2. Command and maintenance contract

- [x] 2.1 Test and implement command registration, text/JSON stdout, stderr, invalid options and supported signal cleanup without creating an Echo client; run focused command/application tests.
- [x] 2.2 Update docs/cli.md command reference and README example, generated transcription CLI reference and CHANGELOG; verify `make lint-agent-plugins`.
- [x] 2.3 Require non-interactive gh-only issue creation in maintainer skill and verify the rule and example against the actual issue #78 workflow.

## 3. Integration and delivery

- [x] 3.1 Run `make lint-openspec`, `git diff --check`, `make test-unit` and `make lint-all`; record results and local media acceptance.
- [x] 3.2 Review diff, push branch and open PR targeting dev with `Closes #78`; verify PR #81 targets dev and record local verification.

## Workflow follow-up

- Before reporting delivery complete, wait for green PR CI and process all agent review comments/threads. The current CI and hosted review results are recorded on PR #81.

- Archive only after the PR merges. Merge and release are outside this request.
