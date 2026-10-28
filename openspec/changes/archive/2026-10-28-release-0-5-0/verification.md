# Stable 0.5.0 verification

Date: 2026-10-28 (Asia/Bishkek). Issue #68. Initial dev source: b5a5adeb65acf5cbeccc6b2714102bfc2ff39421.

The user explicitly directed final stable publication with dev delivery before main promotion. This is release authorization; no particular manual-test outcome is fabricated. Existing RC-3 public acceptance is historical evidence, not a substitute for fresh stable tag/public acceptance.

## Preparation

The cumulative dated 0.5.0 changelog covers all three candidates, leaving Unreleased empty and preserving candidate sections byte-for-byte. Composer selects ^0.5; stable installer examples are prepared with explicit pending-publication notices. README states that Latest commands require stable 0.5.0 or newer, avoiding a frozen claim that Latest is still 0.4.0 inside the future stable tag. The installation guide makes publication/version checks explicit, and the RC-3 verified skill baseline remains historical until stable assets are actually verified. No PHP runtime, installer, packaging or CI behavior changes.

- `make lint-openspec`: all 29 items pass in strict mode.
- `make test-unit`: 405 tests, 1170 assertions, four opt-in skips.
- `make lint-all`: OpenSpec, plugin/reference drift, CS Fixer, PHPStan and Rector pass.
- `python3 -m unittest discover -s build/tests -v`: all 80 tests pass with loopback/process inspection permissions.
- `git diff --check`: passes.
- Independent read-only stable review: no actionable findings; confirmed cumulative scope, historical notes, stable examples, dev-first lifecycle and retained public/Latest gates.

Docker checks reuse `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk COMPOSE_IGNORE_ORPHANS=True`. No live Echo submission, personal credentials/configuration change or persistent installation is part of preparation.

## Delivery and publication

- [Preparation PR #86](https://github.com/mesilov/rarus-echo-php-sdk/pull/86) merged into dev at 2026-10-28 14:32:30 UTC, producing 53c817816feff9aee3a2e995aef94cfeb4443094. Reviewed source 1cf945b4b7b5d69f003b466b0087caccae7d4045 has the identical tree ed980051ef672497324510308fd132a5261017b8. [Native preparation CI 40702204384](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40702204384) passed contracts, all four packages and aggregate archive verification; quality/unit checks passed. No unresolved review threads were present. Independent follow-up review confirmed future stable-tag availability text remains valid before and after publication.
- [Dev-to-main PR #87](https://github.com/mesilov/rarus-echo-php-sdk/pull/87) merged at 2026-10-28 15:34:40 UTC after [native promotion CI 40706975704](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40706975704) and all quality/unit checks passed. The first Linux amd64 run hit a 30-second FFmpeg source connection timeout; the next failed fetching the GNU libiconv directory before PHP runtime compilation. Both upstreams were independently reachable with HTTP 200, and the subsequent rerun passed on the unchanged source. No checksum, acceptance or source-identity gate was relaxed. Independent full-promotion review found no blockers; hosted review status remained running without findings at merge, and reviewThreads was empty. Its running summary is not represented as a completed approval.
- Main merge and exact tag source: 43a76b3e8ae837e85f31e1104ca4259137e988a1. Its tree equals the checked dev and preparation source. Lightweight tag 0.5.0 was created without moving any previous version.
- A draft non-prerelease 0.5.0 with cumulative curated notes is prepared for [tag workflow 40717449762](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40717449762). Public assets and Latest publication remain pending until all four native packages and final archive upload pass.
- Packagist p2 metadata already lists 0.5.0 with source/dist reference 43a76b3e8ae837e85f31e1104ca4259137e988a1. This verifies SDK tag indexing, not completed CLI public-asset acceptance.
- README/docs local-file link checks pass across ten Markdown files. Existing CONTRIBUTING forge-navigation links are outside this local-file check.
- Related #72 PRs #75 and #80 are merged with all native/quality/unit/archive checks green and no review threads. Their stable Latest availability criterion remains pending actual publication.


## Stable publication and public acceptance

- Tag workflow https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40717449762 passed all four native packages, aggregate archive verification and upload. Existing draft stable release kept assets private until the complete accepted set existed.
- https://github.com/mesilov/rarus-echo-php-sdk/releases/tag/0.5.0 was published at 2026-10-28 16:05:31 UTC with prerelease=false/draft=false and five assets. Latest was explicitly set and verified as 0.5.0. Earlier RC tags/assets remain unchanged.
- Public acceptance https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40722532306 passed contracts and all four native targets, using anonymous public downloads, exact tag/main ancestry, identity/checksums, repeat installation, saved configuration/PATH preservation and CLI/media smoke with controlled local HTTP fixtures.
- Main release-source Tests 40717374717 and Lint 40717374693 passed; Packagist source/dist both reference 43a76b3e8ae837e85f31e1104ca4259137e988a1.

## Actual Latest download verification

All four `releases/latest/download/<archive>` routes and SHA256SUMS were actually downloaded via gh. Each size/API asset digest matched, and the complete set passed the shared checksum/source/archive validator for stable 0.5.0 and source 43a76b3. This verifies file content, not only HEAD availability. Non-macOS copies were temporary and cleaned after validation.

| Asset | SHA-256 |
| --- | --- |
| rarus-echo-linux-amd64.tar.gz | fc8aa021ba05efb8bc6220453230606b97a249f402800c5c9d1099f6bb857904 |
| rarus-echo-linux-arm64.tar.gz | 9cf8f58d6f6c155141443590c96b1cbea1115603bf9b0e32f8aa17d999297d0c |
| rarus-echo-macos-arm64.tar.gz | 5431a4c6dea3f7ed623e766672bede1c6f63cc1d1c0bf088222f824953f5d239 |
| rarus-echo-windows-amd64.zip | 0971dfce6c98d4560042f4ae3be8eeb28dcdd75dad726b9cac619552e3dd5162 |

The public macOS archive, SHA256SUMS, exact tagged/main installer, complete extracted package and handoff README/verification.json are saved under downloads/0.5.0/. The installer hash is af114c8acf1ea5fab2a1a395d523797861b4a930abd63c2b82a610518cbccbdc. Native --version reports RARUS Echo CLI 0.5.0 (source 43a76b3e8ae837e85f31e1104ca4259137e988a1); structured extract-audio help passes. A generated reference from this published executable matches the source's seven project-command contracts.

An additional macOS installation omitted the version option and selected stable 0.5.0 from Latest. HOME/config/data/cache/temp directories and installation prefix were isolated; --no-modify-path preserved profiles. No personal installation, persistent PATH or Echo credentials were changed. This is automated technical acceptance, not a fabricated report of human manual testing.

## Documentation closeout

After actual stable public verification, README and installation availability notices report observed Latest state, Composer targets ^0.5 and the verified CLI map advances to stable 0.5.0. The CLI/installer behavior and execution authorization rules are unchanged. #72's original PRs #75/#80 and stable-link criteria are verified, allowing its documentation archive after merge. Completed release implementation is archived after merged release PRs #86/#87 and accepted public publication. Final closeout PR CI/reviews and ordered dev/main delivery remain external workflow steps; their completion is not claimed prematurely in this source record.


Final local closeout gates pass after archive/baseline synchronization: strict OpenSpec 28 items, make test-unit 405 tests/1170 assertions with four opt-in skips, make lint-all, skill quick validation, generated reference comparison with the actual published stable executable and git diff --check. README/docs local file targets pass; historical RC changelog remains byte-for-byte unchanged and Unreleased is empty. Independent closeout review found no actionable findings and verified preserved canonical scenarios, source/checksums and unchanged skill authorization policy. Publication facts were verified by the primary agent; the reviewer used supplied evidence/local records. Final dev/main closeout delivery remains gated by its own PR CI and review checks.
