## 1. OpenSpec

- [x] 1.1 Create proposal, design, spec delta and task list for issue #93.
- [ ] 1.2 Validate OpenSpec with `make lint-openspec`.

## 2. Timeline measurement (`DecodedTimeline`)

- [ ] 2.1 Replace the `Ambiguous discontinuous decoded audio timeline` exception with recording of the event and resynchronization of the expected position to the current frame.
- [ ] 2.2 Expose discontinuity count, total gap seconds and total overlap seconds as readonly properties.
- [ ] 2.3 Compute `duration` as timestamp span when discontinuities exist; keep `samples / rate` for a continuous timeline.
- [ ] 2.4 Treat a frame without a timestamp as continuing the previous frame; skip and count unparsable lines; fail only when no frame was parsed.

## 3. Preparation route (`PreparationPolicy`, `MediaPreparer`)

- [ ] 3.1 Pass the discontinuity flag from `MediaPreparer` into `PreparationPolicy::plan()`.
- [ ] 3.2 For a discontinuous timeline, disallow stream copy and insert `aresample=async=1:min_hard_comp=0.001:min_comp=0.001:first_pts=0` after `asetpts`; keep arguments byte-identical for a continuous timeline.
- [ ] 3.3 Return 16-bit precision from `fallbackPrecision()` for lossy codecs.
- [ ] 3.4 Emit a stderr progress line `repairing: <file> timeline_gaps=N silence_ms=X trimmed_ms=Y` when repairs occur.
- [ ] 3.5 Require zero discontinuities in the prepared output in addition to the existing 50 ms duration check.

## 4. Upload and temporary limits

- [ ] 4.1 Raise `FileValidator::MAX_FILE_SIZE` to 1 GiB and update its docblock.
- [ ] 4.2 Make `PreparedFileSet` use `FileValidator::getMaxFileSize()` for the upload limit and update the error text.
- [ ] 4.3 Raise the temporary-output budget in `PreparedFileSet` to 2 GiB and update the error text.
- [ ] 4.4 Upload a real 0.9-1 GiB audio file to Echo and confirm it is accepted and transcribed; record memory and timing. If Echo rejects it, stop and escalate instead of merging the limit change.

## 5. Decode-error tolerance (may be split into a separate PR)

- [ ] 5.1 Add a fixture with damaged audio packets in the middle of the track to `tests/Fixtures/Media/generate.py` and `manifest.json`.
- [ ] 5.2 Choose decoder flags (drop `-xerror`, `-err_detect ignore_err`, `-fflags +discardcorrupt` as candidates) so that preparation completes on that fixture; accept partial `ffprobe` frame output.
- [ ] 5.3 Revisit the existing `corrupt.mp4` expectation: failure only when no frame decodes.

## 6. Tests

- [ ] 6.1 `DecodedTimelineTest`: replace the gap-rejection test with gap accounting; add overlap, backward timestamp, multiple discontinuities, frame without timestamp, unparsable line, empty timeline failure.
- [ ] 6.2 `PreparationPolicyTest`: discontinuous timeline at zero offset uses FLAC with `aresample`, not copy; lossy source yields 16-bit; continuous arguments unchanged apart from precision.
- [ ] 6.3 `MediaPreparerTest`: `repairing:` line and failure when the output is still discontinuous.
- [ ] 6.4 Add `gapped.mp4` (AAC with a ~100 ms cut and tone markers before and after) and a `MediaPreparerCorpusTest` case asserting the marker after the gap stays within 50 ms of its source position.
- [ ] 6.5 Update `FileValidatorTest` and `PreparedFileSetTest` for the new limits.
- [ ] 6.6 Manual acceptance on the recording from issue #93: `rarus-echo extract-audio`, then `submit --wait` when credentials are available.

## 7. Documentation

- [ ] 7.1 Update `docs/cli.md`: repair behaviour, `repairing:` line, 1 GiB upload and 2 GiB temporary limits, 16-bit fallback for lossy sources.
- [ ] 7.2 Add `CHANGELOG.md` Unreleased entries: Fixed (timeline repair, #93) and Changed (16-bit fallback, limits).
- [ ] 7.3 Confirm `make lint-agent-plugins` stays green (no CLI option changes expected).

## 8. Verification and PR

- [ ] 8.1 Run `make lint-openspec`.
- [ ] 8.2 Run `git diff --check`.
- [ ] 8.3 Run `make test-unit`.
- [ ] 8.4 Run `make lint-all`.
- [ ] 8.5 Open a PR to `dev` with `Closes #93`, verify CI and handle agent review threads.
