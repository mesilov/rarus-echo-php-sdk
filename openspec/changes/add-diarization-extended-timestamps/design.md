## Context

The live OpenAPI schema at `https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json` describes `POST /v1/async/transcription` as a multipart upload endpoint. It exposes `task-type` as one header and `timestamps-extended` as a separate integer header. The `timestamps-extended` description says it enables extended timestamps for diarization in `[HH:MM:SS.SSS - HH:MM:SS.SSS]` form.

The SDK already models request headers in immutable `TranscriptionOptions` and a mutable builder. The CLI builds those options in `SubmitCommand`, then submits through `EchoClientInterface`. The missing piece is a first-class option for the existing API header.

## Goals / Non-Goals

**Goals:**

- Preserve the current `task-type=diarization` model instead of introducing a combined task type.
- Add a boolean SDK option that maps directly to `timestamps-extended: 1|0`.
- Add a CLI flag that forwards the same option.
- Keep the default behavior compatible by leaving extended timestamps disabled unless requested.
- Cover the new behavior with focused unit tests.

**Non-Goals:**

- Do not add transcript polling or result parsing changes.
- Do not add a new OpenAPI refresh pipeline.
- Do not add paid API coverage to the default unit-test path.
- Do not change authentication, upload multipart encoding, or PSR discovery behavior.

## Decisions

1. Model extended timestamps as `bool $timestampsExtended`.
   - Rationale: the schema exposes a binary `timestamps-extended` header with `0` and `1` values.
   - Alternative rejected: a new `TaskType` like `diarization_with_timestamps`. That would not match the OpenAPI request shape and would hide the separate header.

2. Emit `timestamps-extended` from `TranscriptionOptions::toHeaders()` for every submission.
   - Rationale: existing boolean options (`censor`, `speakers-correction`, `store-file`, `low-priority`) always emit `0|1`, so this keeps header generation predictable and testable.
   - Alternative rejected: emit the header only when true. That creates a special case for one boolean option and makes default header tests less consistent.

3. Add `SubmitCommand --timestamps-extended` as a VALUE_NONE flag.
   - Rationale: the CLI already uses presence flags for boolean API headers.
   - Alternative rejected: allow arbitrary headers through CLI. That would expand the public surface beyond issue #22 and weaken typed validation.

## Risks / Trade-offs

- The API may reject requests when the account has insufficient funds. Mitigation: keep paid coverage in the explicit integration suite and let unit tests verify request construction without making API calls.
- Existing callers might compare full header arrays. Mitigation: document the new default header and keep the value disabled (`0`) by default.
- Docker CLI behavior depends on publishing a new image after merge. Mitigation: include README and PR validation notes that the production image must expose `--timestamps-extended`.
