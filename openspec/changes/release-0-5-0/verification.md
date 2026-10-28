# Stable 0.5.0 verification

Date: 2026-10-28 (Asia/Bishkek). Issue #68. Initial dev source: b5a5adeb65acf5cbeccc6b2714102bfc2ff39421.

The user explicitly directed final stable publication with dev delivery before main promotion. This is release authorization; no particular manual-test outcome is fabricated. Existing RC-3 public acceptance is historical evidence, not a substitute for fresh stable tag/public acceptance.

## Preparation

The cumulative dated 0.5.0 changelog covers all three candidates, leaving Unreleased empty and preserving candidate sections byte-for-byte. Composer selects ^0.5; stable installer examples are prepared with explicit pending-publication notices. README's current availability and the RC-3 verified skill baseline remain truthful until stable assets are actually verified. No PHP runtime, installer, packaging or CI behavior changes.

- `make lint-openspec`: all 29 items pass in strict mode.
- `make test-unit`: 405 tests, 1170 assertions, four opt-in skips.
- `make lint-all`: OpenSpec, plugin/reference drift, CS Fixer, PHPStan and Rector pass.
- `python3 -m unittest discover -s build/tests -v`: all 80 tests pass with loopback/process inspection permissions.
- `git diff --check`: passes.
- Independent read-only stable review: no actionable findings; confirmed cumulative scope, historical notes, stable examples, dev-first lifecycle and retained public/Latest gates.

Docker checks reuse `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk COMPOSE_IGNORE_ORPHANS=True`. No live Echo submission, personal credentials/configuration change or persistent installation is part of preparation.

## Delivery and publication

Preparation PR into dev, separate dev-to-main release promotion, immutable tag build, stable Latest publication, four-platform public acceptance and final availability/skill documentation remain tracked in tasks.md. Publication is not claimed by this preparation record.
