## 1. OpenSpec

- [x] 1.1 Create proposal, task list, and spec delta for issue #41.
- [x] 1.2 Validate OpenSpec with `make lint-openspec`.

## 2. Skill guidance

- [x] 2.1 Rewrite `Command Selection` so the published Docker CLI is the unconditional default with no host PHP / local binary probing.
- [x] 2.2 Keep local binary execution documented as an explicit opt-in (user request or Docker unavailable).
- [x] 2.3 Preserve `--pull=always` and credential-safety guidance.

## 3. Release notes

- [x] 3.1 Add a `CHANGELOG.md` `Unreleased` entry for the skill guidance change.

## 4. Verification

- [x] 4.1 Run `make lint-openspec`.
- [x] 4.2 Run `make lint-agent-plugins`.
- [x] 4.3 Run `git diff --check`.
- [x] 4.4 Run `make lint-all`.
