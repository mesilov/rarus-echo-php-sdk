# Verification

Date: 2026-10-28 (Asia/Bishkek). Issue #83. Target: exact prerelease 0.5.0-rc-3.

## Prepared source

Base origin/dev: 1332d5be5d4da3098716cc6ff74a471554877f9a. RC-3 assembles the actual merged changes after RC-2 without changing PHP runtime or CI behavior. Historical RC sections remain unchanged and Unreleased is empty. Explicit installer and Composer examples select RC-3; README retains permanent Latest links and the truthful stable 0.4.0 notice. Preparation retained the historical RC-2 skill baseline; closeout advances it only after public RC-3 verification as recorded below.

Already-merged changes from PRs #77, #76, #79 and #81 are archived in dependency order. Their original files and specification deltas are preserved; the newly created installed-cli-transcription-skill canonical Purpose is filled explicitly because OpenSpec archive inserted a placeholder. Release change remains active until its PR has merged.

## Local checks

- `make lint-openspec`: 29 items pass, strict mode.
- `make test-unit`: 405 tests, 1170 assertions, four opt-in skips; baseline and prepared release both pass.
- `make lint-all`: OpenSpec, plugin/reference drift, PHP CS Fixer, PHPStan and Rector all pass.
- `python3 -m unittest discover -s build/tests -v`: 80 tests pass. Initial sandbox run was blocked from loopback sockets and process inspection; the same tests pass with host permissions.
- `python3 build/tests/shell-installer-check.py`: 26 tests pass with one native-archive fixture skipped; actual native package tests remain required in CI.
- `git diff --check`: passes.
- Independent read-only release review: no actionable findings; confirmed archived files/deltas, release scope and retained stable/manual gates. GitHub state is verified by the primary agent; the reviewer had no network access.

Docker commands use `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk COMPOSE_IGNORE_ORPHANS=True` to reuse the existing project network after earlier tasks exhausted the host's new-network address pools. Runtime source is unchanged; no live Echo submission or personal installation/configuration changes are part of local preparation.

## Publication

- Release PR [#84](https://github.com/mesilov/rarus-echo-php-sdk/pull/84) merged into main at 2026-10-28 10:50:56 UTC after all checks passed. Its initially dev-targeted base was changed to main as the single release promotion, following RC-2. Head remained d085001f99f5d9e6861598e296d733853e188289 throughout.
- [PR native packaging 40671858184](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40671858184) passed contracts, all four platform jobs and aggregate archive verification. Hosted Codex reported no findings; reviewThreads was empty immediately before merge. A second independent review of the complete RC-2-to-release source found no actionable release blockers and verified source-aware public acceptance/extraction coverage.
- Main merge SHA and exact tag source: 7b954abd4220335c416027653ae1e2f9f48d8d87. Both the reviewed head and main have tree bdeb582d68899f0d62ea72372977b7d4dd2d58da; dev base 1332d5b is its ancestor. Lightweight tag 0.5.0-rc-3 was created without replacing an earlier tag.
- [Tag workflow 40675380487](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40675380487) passed all four native packages, aggregate archive validation and asset upload. The release was prepared as a draft with curated notes, so completed uploads did not expose an incomplete public release.
- [0.5.0-rc-3](https://github.com/mesilov/rarus-echo-php-sdk/releases/tag/0.5.0-rc-3) was published at 2026-10-28 11:16:32 UTC with four archives plus SHA256SUMS, prerelease=true and draft=false. Latest stable was separately verified as 0.4.0 before and after publication.
- [Public acceptance 40678679327](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40678679327) passed its contracts and all four native platform jobs. They downloaded anonymous public assets, resolved the exact tag in main history, verified identities/checksums, installed with public main scripts twice, preserved user configuration/PATH and exercised source-aware CLI/media behavior using isolated local HTTP fixtures, including local extraction without Echo requests.
- All five main push workflows passed for 7b954ab, including Docker publication and installer fixtures. The push-triggered public-acceptance workflow tests contracts; the separate exact-version dispatch above supplies actual public-installation acceptance.
- Packagist p2 metadata lists 0.5.0-rc-3 with source and dist reference 7b954abd4220335c416027653ae1e2f9f48d8d87.

## Public checksums

| Asset | SHA-256 |
| --- | --- |
| rarus-echo-linux-amd64.tar.gz | 2cac7b4e22d4ca0d229ce648bb23d4b761aa957c5a64fcf1652f2184728f38c4 |
| rarus-echo-linux-arm64.tar.gz | 539d34fe71685254e0f8b4c89ac2c04a4d6b8bfba0e94a364c20343aded0f771 |
| rarus-echo-macos-arm64.tar.gz | 78a3110d0fd3e4faec87bd9985c9c7d9b5bf1ee682eb6f669220b837a8c377ed |
| rarus-echo-windows-amd64.zip | fdbc25136aa5f246c8cea5cd7abd52bb7e1bccb70249f4127a9954900f3305e6 |

GitHub asset digests and the published manifest agree; each native public-acceptance target verified its archive against that four-record manifest.

## Local handoff and verified skill baseline

The public macOS arm64 archive, SHA256SUMS and source-matching installer are saved in the primary workspace at downloads/0.5.0-rc-3, with a complete extracted package, README.txt and verification.json. The archive's composition, embedded source/version/target and compression provenance were validated before extraction and execution. The installer matches the tagged/main script byte-for-byte and has SHA-256 af114c8acf1ea5fab2a1a395d523797861b4a930abd63c2b82a610518cbccbdc.

The actual downloaded executable reports RARUS Echo CLI 0.5.0-rc-3 (source 7b954abd4220335c416027653ae1e2f9f48d8d87); its structured extract-audio help passes. The reference generator run with this executable matches the source command map for all seven project commands. Historical RC-2 explicitly rejects help for extract-audio; this demonstrates why the verified map baseline must advance. After four-platform public acceptance, the skill, installation reference, generator header and plugin guide advance to RC-3; no execution policy or CLI behavior changes. Skill quick validation passes.

Personal connection configuration and persistent PATH were not changed. Human connection/workflow acceptance and stable publication remain in #68; #72 remains open for stable Latest assets.

## Closeout delivery

[PR #85](https://github.com/mesilov/rarus-echo-php-sdk/pull/85) synchronizes the reviewed main release source and this evidence into dev. Release implementation is complete and release PR #84 is merged, allowing this OpenSpec archive. Final closeout CI/review and merge remain delivery steps; this evidence record does not claim their completion prematurely.

After archive and baseline synchronization, `make lint-openspec` passes all 28 items, `make test-unit` passes 405 tests/1170 assertions with four opt-in skips, and `make lint-all` and `git diff --check` pass. The skill quick validator passes; the generated reference matches the actual published RC-3 executable for all seven project commands. Changelog still has an empty Unreleased and dated RC-3, preserving earlier RC history. The original draft #85 source also passed four native packaging jobs and archive verification before final closeout updates; the updated head requires its own CI/review before merge.

Independent closeout review found no actionable issues and verified the source tree identity, saved checksums/installer hash, preserved canonical scenarios and unchanged skill execution policy. External publication and CI state were verified by the primary agent; the reviewer used the supplied evidence and local records.
