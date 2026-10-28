# Tasks

## 1. Archive contract and provenance

- [x] 1.1 Add failing regression coverage for four reduced archives and exact historical RC-2 acceptance; run `python3 -m unittest discover -s build/tests -p test_packaging.py -v`.
- [x] 1.2 Update package copy list and strict archive contract while preserving RC-1/RC-2 original identities; verify packaging and release acceptance tests.
- [x] 1.3 Separate source packaging attribution from distributed runtime notices, update build/README.md and CHANGELOG.md; verify references and `git diff --check`.

## 2. Installer compatibility

- [x] 2.1 Update shell fixtures and test old-to-new notices replacement; confirm failure before the fix and pass afterward with `python3 build/tests/shell-installer-check.py`.
- [x] 2.2 Accept optional historical licenses in both installers while preserving frozen RC-1 requirements; add native Windows archive/upgrade regression assertions for CI.

- [x] 2.3 Integrate dev compression (#77) without restoring removed licenses; verify four compressed archive targets, rejection of compression in frozen RC-2, and old-to-compressed upgrade assertions in packaging and shell tests.

## 3. Integration verification

- [x] 3.1 Run `make lint-openspec`, `git diff --check`, all Python build tests, shell installer tests, `make test-unit` and `make lint-all`; record any external blockers.

## Workflow follow-up

- Open a PR against dev closing #73, verify all native CI checks and address inline/top-level agent reviews before reporting issue completion.

- Archive clean-binary-archive-notices only after the linked PR has merged.
