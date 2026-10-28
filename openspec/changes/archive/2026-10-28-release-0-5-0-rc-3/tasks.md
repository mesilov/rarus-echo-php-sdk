# RC-3 release plan

## 1. Prepare from dev

- [x] 1.1 Read #83, verify origin/dev and create the prepared issue worktree; run baseline unit tests (405 tests, 1170 assertions, four opt-in skips).
- [x] 1.2 Archive the already-merged compression, archive-notice, installed-CLI skill and extract-audio changes in dependency order; keep #72's stable gate active.
- [x] 1.3 Assemble dated RC-3 changelog with empty Unreleased and historical RC entries; update explicit installation/Composer examples and selected-candidate spec.
- [x] 1.4 Run make lint-openspec, make test-unit, make lint-all, Python packaging/installer contracts and git diff --check; record evidence.

## 2. Review and promote

- [x] 2.1 Open dev-based PR #84 and verify its exact source with all applicable CI checks and agent review threads. The initially dev-targeted PR was retargeted to main as the single release promotion, following the RC-2 process.
- [x] 2.2 Merge #84 into main after all four native packages, archive verification, quality and unit checks passed; hosted review and independent full-promotion review found no actionable issues.
- [x] 2.3 Verify main 7b954abd4220335c416027653ae1e2f9f48d8d87 has the exact reviewed tree bdeb582d68899f0d62ea72372977b7d4dd2d58da and create immutable tag 0.5.0-rc-3 on that source.

## 3. Publish and accept

- [x] 3.1 Wait for four native tag builds, aggregate archive verification and publication; verify prerelease metadata, four archives, SHA256SUMS and unchanged Latest stable.
- [x] 3.2 Dispatch exact-version public release acceptance on main and verify all four native targets pass.
- [x] 3.3 Save the public macOS arm64 archive, SHA256SUMS and main installer under downloads/0.5.0-rc-3; verify checksum, identity and local command map.

## 4. Closeout

- [x] 4.1 Advance the transcription-skill verified baseline only after public RC-3 acceptance; synchronize the generator/reference and installation guidance.
- [x] 4.2 Record immutable source/run/download evidence and prepare the archived release closeout for PR #85 after merged #84 and successful public acceptance.

## Workflow follow-up

- Deliver PR #85 into dev only after its final CI and agent-review checks pass; this synchronization preserves the reviewed release ancestry and adds the public evidence and verified skill baseline.
- Update #68 with RC-3 handoff while retaining manual/stable gates, keep #72 open for stable Latest checks, and close #83 only after closeout delivery passes.
- The completed release implementation is archived after release PR #84 merged and public acceptance succeeded; this archive does not claim the subsequent closeout PR is already merged.
