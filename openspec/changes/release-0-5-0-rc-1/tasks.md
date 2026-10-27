# Tasks

## 1. Prepare release

- [x] 1.1 Archive installer change after PR #58 merge, update CHANGELOG/README for exact 0.5.0-rc-1 and validate OpenSpec.
- [x] 1.2 Add public-release native acceptance helper/workflow with focused offline contracts; verify tests and four-platform workflow mapping.
- [x] 1.3 Run `make lint-openspec`, `make test-unit`, `make lint-all`, packaging/acceptance contracts and `git diff --check`.

## 2. Publish and accept

- [ ] 2.1 Open release PR into main from the dev-based branch, wait for green CI, handle agent review and merge; verify source commit in main.
- [ ] 2.2 Tag exact main commit 0.5.0-rc-1; verify successful native build/publication workflow, four archives, SHA256SUMS and prerelease/latest metadata.
- [ ] 2.3 Dispatch public release acceptance on main; verify all four native jobs using published RC binaries and record run/source evidence in issue #59.

## Workflow follow-up

- Archive release change only after merge and successful published-release acceptance.
- Synchronize release documentation back to dev if its branch does not already contain the main release commit.
