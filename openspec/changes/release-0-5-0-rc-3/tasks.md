# RC-3 release plan

## 1. Prepare from dev

- [x] 1.1 Read #83, verify origin/dev and create the prepared issue worktree; run baseline unit tests (405 tests, 1170 assertions, four opt-in skips).
- [x] 1.2 Archive the already-merged compression, archive-notice, installed-CLI skill and extract-audio changes in dependency order; keep #72's stable gate active.
- [x] 1.3 Assemble dated RC-3 changelog with empty Unreleased and historical RC entries; update explicit installation/Composer examples and selected-candidate spec.
- [x] 1.4 Run make lint-openspec, make test-unit, make lint-all, Python packaging/installer contracts and git diff --check; record evidence.

## 2. Review and promote

- [ ] 2.1 Open the release documentation PR into dev; verify all applicable CI checks and agent threads before merge.
- [ ] 2.2 Open the checked dev release PR into main; verify source/content, all required checks and agent threads before merge.
- [ ] 2.3 Verify the selected full main SHA and create exact immutable tag 0.5.0-rc-3.

## 3. Publish and accept

- [ ] 3.1 Wait for four native tag builds, aggregate archive verification and publication; verify prerelease metadata, four archives, SHA256SUMS and unchanged Latest stable.
- [ ] 3.2 Dispatch exact-version public release acceptance on main and verify all four native targets pass.
- [ ] 3.3 Save the public macOS arm64 archive, SHA256SUMS and main installer under downloads/0.5.0-rc-3; verify checksum, identity and local command map.

## 4. Closeout

- [ ] 4.1 Advance the transcription-skill verified baseline only after public RC-3 acceptance; synchronize the generator/reference and installation guidance.
- [ ] 4.2 Record immutable source/run/download evidence, archive the release after merge, and synchronize closeout to dev through green reviewed CI.
- [ ] 4.3 Close #83 only after its criteria pass. Update #68 with RC-3 handoff while retaining manual/stable gates and keep #72 open for Latest checks.
