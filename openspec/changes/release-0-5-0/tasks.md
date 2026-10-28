# Stable release plan

## 1. Prepare and deliver to dev

- [x] 1.1 Read #68/#72, verify branches and explicit stable authorization; create a fresh dev-based worktree preserving unrelated work.
- [x] 1.2 Assemble cumulative 0.5.0 changelog, preserve RC history and empty Unreleased; prepare Composer ^0.5 and pinned stable documentation.
- [x] 1.3 Run make lint-openspec, git diff --check, make test-unit, make lint-all and packaging contracts; record evidence and independent review.
- [ ] 1.4 Open preparation PR into dev, verify all applicable CI/reviews and merge the checked source.

## 2. Promote and tag main

- [ ] 2.1 Open a separate dev-to-main release PR; verify all CI and agent review threads before merge.
- [ ] 2.2 Verify main tree/source identity and create immutable tag 0.5.0 on the checked main commit.

## 3. Publish and accept stable

- [ ] 3.1 Wait for four native tag packages and archive verification/upload; publish the draft as stable Latest only after all five assets exist.
- [ ] 3.2 Run public-release acceptance for exact 0.5.0 on all four platforms; verify Packagist source/dist matches tag.
- [ ] 3.3 Verify all four Latest download routes and default stable installation; save the public macOS package, checksum, installer and handoff.

## 4. Final documentation

- [ ] 4.1 After public verification, update README/install availability, stable skill baseline and canonical specs; fulfill #72's Latest acceptance.
- [ ] 4.2 Record immutable release evidence, archive completed OpenSpecs after their PRs merged and deliver final closeout to dev/main through green reviewed checks.

## Workflow follow-up

Close #68 and #72 and the milestone only after their actual release and Latest criteria pass. Preserve historical RC test records; do not claim a specific human manual-test result without evidence.
