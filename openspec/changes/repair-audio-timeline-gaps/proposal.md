## Why

Issue #93: `rarus-echo submit` (CLI 0.5.0) rejects an ordinary 2-hour call recording with `Ambiguous discontinuous decoded audio timeline` before anything is uploaded. The selected AAC track has a single 99.5 ms timestamp gap. Recording software routinely produces such defects, and the CLI must tolerate partially damaged recordings instead of refusing to transcribe them.

Diagnosis on the reported file also showed that fixing the gap check alone is not enough: the same file then fails the upload-size limit, because lossy audio that needs timeline normalization is converted to 24-bit FLAC (1.13 GB for this recording).

## What Changes

- Decoded-timeline measurement records gaps and overlaps instead of throwing; preparation repairs them (gaps become decoded silence, overlaps are trimmed) so speech stays at its original timeline position, and reports each repair on stderr.
- A discontinuous source never uses the stream-copy route; it is converted through the verified FLAC fallback with gap filling.
- Damaged recordings are tolerated more broadly: frames without a timestamp, unparsable probe lines and undecodable packets no longer abort preparation as long as audio can still be decoded. Preparation fails only when nothing is recoverable or a resource limit is reached.
- Strict validation moves to the prepared output: it must be continuous and match the expected timeline within 50 ms.
- The lossless fallback for lossy source codecs is encoded as 16-bit FLAC instead of 24-bit. Integer precision of lossless sources is preserved as before.
- **BREAKING (limit value)**: the SDK upload-size limit rises from 500 MiB to 1 GiB (`FileValidator::getMaxFileSize()` returns the new value); the CLI temporary-output budget rises from 1 GiB to 2 GiB. Conditional on Echo accepting a file of that size (see design).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `cli-media-preparation`: source timeline preservation now repairs discontinuities instead of failing; new damaged-recording tolerance requirement; 16-bit fallback for lossy sources; raised upload and temporary-output limits.

## Impact

- Runtime PHP code: `src/Infrastructure/Console/Media/{DecodedTimeline,PreparationPolicy,MediaPreparer,PreparedFileSet}.php`, possibly `MediaProcessRunner.php`; `src/Infrastructure/Filesystem/FileValidator.php` (public SDK limit).
- Tests and fixtures under `tests/Unit/Infrastructure/Console/Media/`, `tests/Unit/Infrastructure/Filesystem/`, `tests/Fixtures/Media/`.
- Documentation: `docs/cli.md`, `CHANGELOG.md`. No CLI options change, so the generated CLI reference is unaffected.
- No new FFmpeg components: `aresample` is already in the compact build (`build/media/config.json`).
- GitHub issue: #93, milestone 0.6.0.
