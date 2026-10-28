# Verification

Date: 2026-10-28 (Asia/Bishkek). Issue #83. Target: exact prerelease 0.5.0-rc-3.

## Prepared source

Base origin/dev: 1332d5be5d4da3098716cc6ff74a471554877f9a. RC-3 assembles the actual merged changes after RC-2 without changing PHP runtime or CI behavior. Historical RC sections remain unchanged and Unreleased is empty. Explicit installer and Composer examples select RC-3; README retains permanent Latest links and the truthful stable 0.4.0 notice. The transcription skill retains its historically verified RC-2 baseline until public RC-3 verification.

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

Pending the checked dev/main PRs, exact tag build, four-platform public acceptance and verified public macOS handoff. No publication or stable/manual acceptance is claimed by this preparation record.
