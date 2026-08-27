## Context

The integration tests already exercise real Echo API behavior, but they mixed duplicated credential checks, stdout logging, hard-coded fixture paths, and an ad hoc core test with debug output. That made the local workflow noisy and easy to misuse.

## Decision

Introduce a shared `IntegrationTestCase` for live-test setup. The base class loads credentials from the environment, skips tests when placeholders are present, validates UUID-shaped credentials without printing values, and provides helpers for `tests/Assets/ru/*.ogg`.

Expose focused Make targets:

- `test-integration-core`
- `test-integration-queue`
- `test-integration-status`
- `test-integration-transcription`

Keep the existing `test-integration` target as the full live suite. The README documents `.env.local` with placeholder values only and makes clear that missing placeholders skip tests instead of failing.

## Risks

- Live tests depend on real Echo API availability and valid local credentials.
- Live API state can make status assertions timing-sensitive, so tests must allow both in-progress and already-successful states where short fixtures can complete quickly.
