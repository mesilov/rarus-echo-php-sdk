## Context

Diagnosis was done on 2026-11-20 against the recording from issue #93 (mp4/isom written by Lavf62.1.100, 1.04 GB, 2 h 05 min 38 s) with FFmpeg 9.0.2. No code was changed during diagnosis.

| Stream | Codec | Parameters | start_time | duration |
|---|---|---|---|---|
| 0 video | h264 High | 1490x720 | 0.040 | 7538.480 |
| 1 audio | AAC LC | 48 kHz stereo | 1.002 | 7535.3955 |

`format.start_time = 0.040`, so audio starts 0.962 s after the playback origin.

Measured with the same probe the CLI uses (`MediaPreparer::timeline()`: `ffprobe -select_streams 1 -show_frames -show_entries frame=best_effort_timestamp_time,nb_samples -of compact=p=0`), 353 217 decoded frames of 1024 samples:

- exactly one discontinuity: frame 243465, `ts=5195.021333` (1:26:35), jump +99.5 ms (about 4776 samples). At packet level, packet `pts=5194.900500` has `duration=0.120833` instead of `0.021333`;
- the "overlap near the start" mentioned in the issue is -0.167 ms, below the existing 2 ms tolerance, and does not trigger the error;
- duration by sample count is 7535.296 s, by timestamps (span) 7535.3955 s.

Root causes:

1. `DecodedTimeline::fromCompactFile()` (`DecodedTimeline.php:45-47`) throws when a frame timestamp deviates more than `max(0.002, 2/rate)` from `start + samples/rate`. This was a deliberate #60 decision ("fail rather than silently rebase speech"), but there is no repair path at all.
2. The tolerance cannot simply be loosened: the current FFmpeg command (`asetpts=PTS-STARTPTS,adelay=...` into FLAC) succeeds on this file but collapses the gap, moving everything after 1:26:35 99.5 ms earlier.
3. Because of the 0.962 s start offset, `PreparationPolicy::plan()` cannot stream-copy and uses FLAC; `fallbackPrecision()` returns 24 bits for lossy codecs. Measured outputs for this file:

| Route | Size |
|---|---|
| FLAC 24-bit (current) | 1 129.7 MB |
| FLAC 16-bit | 462.0 MB (440.6 MiB) |
| AAC copy into m4a | 125.2 MB (does not preserve the timeline as decoded silence) |

The 24-bit output exceeds both the 500 MiB upload limit and the 1 GiB temporary budget, and it also exceeds a raised 1 GiB limit (1 073.7 MB).

Product-owner requirement (2026-11-20): the CLI must be tolerant of recordings that are partially broken by recording-software errors. Product-owner decision (2026-11-20): raise the upload limit to 1 GiB.

## Goals / Non-Goals

**Goals:**

- The recording from #93 prepares successfully and its speech stays on the original timeline within 50 ms.
- Any recording from which audio can still be decoded is prepared; every repair is visible on stderr.
- The CLI never damages audio silently: the prepared output is validated strictly.

**Non-Goals:**

- Adding a lossy encoder (AAC/Opus) to the compact FFmpeg build.
- New CLI options or changes to stdout/JSON contracts.
- Guarantees about Echo recognition timestamp accuracy.

## Decisions

### D1. Record discontinuities in `DecodedTimeline`, do not throw

On a deviation beyond the existing threshold, record the event and resynchronize the expected position to the current frame timestamp (otherwise every later frame is also "discontinuous"). Expose the number of discontinuities, total gap seconds and total overlap seconds. `duration` becomes the span `(last timestamp + last nb_samples/rate) - start`; with zero discontinuities keep the previous `samples/rate` formula so existing exact-float assertions (`0.032`, `7200.0`) hold.

All discontinuities are repairable: forward gaps of any length (total duration is already capped at 86400 s in `PreparationPolicy`), overlaps of any size, timestamps that move backwards. A frame without a timestamp is treated as continuing the previous frame; an unparsable line is skipped and counted. The only remaining failure is a timeline with no parsable frame. Streaming parsing and the frame/line limits are unchanged.

Alternative considered: keep an upper bound (for example 50 ms overlap) and fail beyond it. Rejected because of the tolerance requirement.

### D2. Repair with `aresample=async=1` on the FLAC route

When the source timeline has discontinuities, stream copy is disallowed even at zero offset, and the filter chain becomes:

```
asetpts=PTS-STARTPTS,aresample=async=1:min_hard_comp=0.001:min_comp=0.001:first_pts=0[,adelay=<N>S:all=1]
```

Verified on the #93 file with `min_hard_comp=0.001:first_pts=0`: output has 0 discontinuities, starts at 0.0, lasts 7536.357333 s against the expected 7536.3575 s (span + 0.962 s delay), with 4768 samples (99.33 ms) of silence added. `min_hard_comp` is required: its default is 0.1 s and the gap is 0.0995 s, so without it the gap is not filled. `min_comp=0.001` (default 0.01 s) is intended for 2-10 ms gaps and was **not** verified.

For a continuous timeline the generated arguments stay byte-for-byte identical to today, because `PreparationPolicyTest` and `MediaPrecisionCorpusTest` pin them and the route has recorded Echo acceptance.

Alternative considered: stream-copy into m4a and rely on container timestamps. Rejected: the output would itself be discontinuous and there is no evidence Echo honours edit lists or sample durations.

### D3. 16-bit FLAC for lossy sources

`fallbackPrecision()` returns 16 instead of 24 for non-lossless codecs. A lossy decoder's output has no source integer precision to preserve, 16 bits is ample for speech recognition, and 16-bit FLAC is already in `compatibility.json` (`flac_integer_bits: [16, 24]`), so no new acceptance evidence is needed. Lossless sources keep their 16/24-bit handling.

### D4. Tolerate decode errors

Today `ffmpeg` runs with `-xerror` (`MediaPreparer.php:87`) and a non-zero `ffprobe`/`ffmpeg` exit is fatal, so one corrupt packet aborts preparation. Target behaviour: decode past corrupt packets (candidates: drop `-xerror`, add `-err_detect ignore_err`, `-fflags +discardcorrupt`), let `aresample=async=1` fill the lost region, and accept partial probe output.

This was **not verified**: the #93 file decodes without errors. The exact flags must be chosen against a fixture with damaged packets. The existing `tests/Fixtures/Media/corrupt.mp4` test probably expects failure and needs revisiting: failure remains correct only when no frame decodes. This work is a separate task group so it can be split into its own PR if the decoder flags prove difficult.

### D5. Strict checks on the output

The prepared file must have zero discontinuities, and the existing check `|output duration - plan duration| <= 50 ms` stays. With span-based duration (D1) that check now detects unfilled gaps, which is the guard against silent shifting. A stderr progress line reports repairs, for example `repairing: <file> timeline_gaps=1 silence_ms=99 trimmed_ms=0`.

### D6. Raise limits

`FileValidator::MAX_FILE_SIZE` becomes 1 GiB. `PreparedFileSet` should take the upload limit from `FileValidator::getMaxFileSize()` instead of duplicating the literal. The temporary-output budget is currently 1 GiB minus 66 MiB, which is below the new single-file limit, so it rises to 2 GiB. At about 3.7 MB/min for 16-bit stereo 48 kHz FLAC, 1 GiB is roughly 4 h 50 min of recording.

## Risks / Trade-offs

- [Echo may reject uploads near 1 GiB; 500 MiB was a client-side limit never verified against production] → upload a real 0.9-1 GiB file before merge; on HTTP 413 or a dropped connection, stop, do not raise the limit, and return the question to the product owner.
- [Large multipart upload may hit PHP memory or the HTTP idle timeout] → observe memory and timing during the same acceptance upload.
- [`aresample` could place silence at the wrong position] → corpus fixture with tone markers before and after a cut asserts marker position within 50 ms.
- [Dropping `-xerror` could hide real conversion failures] → output validation (D5) plus the "no decodable frame" failure remain.
- [16-bit quantization of lossy audio] → accepted; inaudible for speech and already an accepted Echo format.
- [`getMaxFileSize()` returns a different value for SDK consumers] → documented in CHANGELOG under Changed.

## Open Questions

- Does production Echo accept and transcribe a ~1 GiB upload? (blocks D6 only)
- Which decoder flags give stable results on damaged packets across the four supported platforms? (D4)
