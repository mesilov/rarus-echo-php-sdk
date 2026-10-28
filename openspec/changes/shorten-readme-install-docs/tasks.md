## 1. Documentation

- [x] 1.1 Extract installation, connection, CLI, PHP, plugin and development material into docs/; preserve detailed instructions and repair links.
- [x] 1.2 Replace README with Latest download table, curl-only installation, first use and documentation links; explain current release availability.
- [x] 1.3 Update maintainer/contributor documentation, CHANGELOG and media documentation spec delta.

## 2. Verification and delivery

- [x] 2.1 Check moved content, local Markdown links/anchors and shell syntax; verify available macOS package/version/help without changing user settings or calling Echo.
- [x] 2.2 Run make lint-openspec, git diff --check, make test-unit and make lint-all; preserve generated CLI reference.
- [ ] 2.3 Push issue branch and open a PR into dev with Closes #72 and the stable-release blocker.
- [ ] 2.4 Check required CI and handle agent review comments on the PR.

## 3. Release dependency (outside this PR)

- [ ] 3.1 After #68 publishes stable binary assets, verify all four Latest downloads and native installation examples before closing #72. Do not create a release for this documentation PR.
