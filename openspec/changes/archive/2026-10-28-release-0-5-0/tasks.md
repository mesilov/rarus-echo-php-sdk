# Stable release plan

## 1. Prepare and deliver to dev

- [x] 1.1 Read #68/#72, verify branches and explicit stable authorization; create a fresh dev-based worktree preserving unrelated work.
- [x] 1.2 Assemble cumulative 0.5.0 changelog, preserve RC history and empty Unreleased; prepare Composer ^0.5 and pinned stable documentation.
- [x] 1.3 Run make lint-openspec, git diff --check, make test-unit, make lint-all and packaging contracts; record evidence and independent review.
- [x] 1.4 Merge preparation PR #86 into dev at 53c817816feff9aee3a2e995aef94cfeb4443094 after green quality/unit/native/archive CI and review-thread checks.

## 2. Promote and tag main

- [x] 2.1 Merge separate dev-to-main PR #87 after all CI passed on source 53c8178 and review threads were checked; independent full-promotion review found no blockers.
- [x] 2.2 Verify main 43a76b3e8ae837e85f31e1104ca4259137e988a1 has the exact reviewed tree ed980051ef672497324510308fd132a5261017b8 and create immutable tag 0.5.0.

## 3. Publish and accept stable

- [x] 3.1 Wait for four native tag packages and archive verification/upload; publish the draft as stable Latest only after all five assets exist.
- [x] 3.2 Run public-release acceptance for exact 0.5.0 on all four platforms; verify Packagist source/dist matches tag.
- [x] 3.3 Verify all four Latest download routes and default stable installation; save the public macOS package, checksum, installer and handoff.

## 4. Final documentation

- [x] 4.1 After public verification, update README/install availability, stable skill baseline and canonical specs; fulfill #72's Latest acceptance.
- [x] 4.2 Record immutable public evidence and prepare completed release/documentation archives after their linked PRs merged. Final closeout delivery follows the gated workflow below.

## Workflow follow-up

Deliver the same checked closeout source to dev first and main second. Their separate PR CI can run in parallel, but main merge requires completed dev delivery and exact tree equality. Check all CI and actual review threads before each merge; close #68/#72 and milestone only after delivery and actual release/Latest criteria pass. Preserve historical RC test records; no specific human manual-test outcome is claimed.
