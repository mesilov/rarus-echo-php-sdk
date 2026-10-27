# Tasks

## 1. POSIX installer

- [ ] 1.1 Adapt b24cli installer/tests for all three Unix targets, Echo identity and notices; verify red/green offline tests with `python3 build/tests/shell-installer-check.py` and `sh -n install.sh`.
- [x] 1.2 Document POSIX commands, options, PATH and publication prerequisites in README; verify examples against `sh install.sh --help` and update attribution/CHANGELOG.

## 2. Windows installer

- [ ] 2.1 Implement PowerShell installer and offline executable-fixture tests for release selection, validation, rollback, PATH and config preservation; verify `powershell -NoProfile -File build/tests/windows-installer-check.ps1` in native Windows CI.
- [x] 2.2 Document Windows commands, options and activation; verify they match script parameters and PowerShell 5.1.

## 3. Integration and delivery

- [ ] 3.1 Add installer CI for Linux amd64/arm64, macOS arm64 and Windows amd64; verify native fixture jobs.
- [x] 3.2 Run `make lint-openspec`, `git diff --check`, `make test-unit`, `make lint-all` and packaging contracts; record outcomes.
- [ ] 3.3 Push issue branch, open PR into dev with `Closes #57`, inspect CI and handle agent comments/review threads before reporting completion.

## Workflow follow-up

- Archive only after the linked PR merges.
- Publish/promote installation scripts and standalone release assets through a separately authorized release.
