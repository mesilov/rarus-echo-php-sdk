# Tasks

## 1. Build identity

- [x] 1.1 Add metadata-backed CLI version and fallback for Composer installs; verify version/SHA and credential-free --version with focused PHPUnit tests.
- [x] 1.2 Document --version in README and align generated skill reference; verify make lint-agent-plugins.

## 2. Standalone packaging

- [x] 2.1 Adapt pinned PHP micro toolchain and platform patches, including fileinfo and Linux arm64; verify config, source rejection tests, and native compilation.
- [ ] 2.2 Stage allowlisted production dependencies and PHAR with Windows temporary payload/macOS signature support; verify PHAR isolation and native extension/upload smoke tests.
- [x] 2.3 Package license-complete platform archives and SHA256SUMS; verify archive contents, executable permissions, checksum tamper rejection, and identity consistency in Python tests.
- [x] 2.4 Add build/README.md, packaging attribution, runtime license copies and export exclusions; verify only intended production paths enter the PHAR.

## 3. CI and installation

- [ ] 3.1 Add PR/dispatch/tag native build matrix and gated GitHub Release upload; verify all four platform checks and final archive set on the PR.
- [x] 3.2 Document standalone platform selection, Unix/Windows checksum/run instructions and release operation in README/CONTRIBUTING; add CHANGELOG Unreleased entry and review instructions against produced artifacts.

## 4. Integration verification and delivery

- [x] 4.1 Run make lint-openspec, git diff --check, make test-unit, make lint-all and packaging contract tests; record actual outputs.
- [ ] 4.2 Push the issue branch, open PR into dev with Closes #53, and check required CI and agent review threads; address verified feedback before reporting completion.

## Workflow follow-up

- Merge only after review and required CI pass; no release tag is created for this issue.
- Archive add-standalone-cli only after the PR merges.
