# Design

## Context

See proposal.md for motivation. MediaPreparer already owns temporary validated audio and returns original paths for audio-only inputs. The new command must retain its output without changing submit or the SDK.

## Goals / Non-Goals

**Goals:** Local extraction with the naming, limits and failure behavior in the spec; preserve source and existing destinations.

**Non-Goals:** Batch extraction, configurable output formats, overwrite mode, API requests, release or merge.

## Decisions

- Reuse MediaPreparationInterface in an internal AudioExtractor; reject unchanged original paths as audio-only. This keeps all codec/timeline/stream decisions in MediaPreparer. A second FFmpeg pipeline would drift; a submit flag would entangle local extraction with credentials/upload options.
- Resolve the supplied parent directory and keep the supplied basename, including for input symlinks. Derive extension only from the prepared output.
- Stage the validated audio inside a private random directory beside the video, then create the destination with an atomic hard link. Unlike rename(), link() cannot replace a concurrently created destination; staging beside the video avoids cross-filesystem linking. Filesystems without hard-link support fail explicitly. Remove the stage and prepared set before reporting success.
- Keep source/prepared/staged/published ownership explicit in try/finally. Clean published output on persistence failure. Handle Unix signals and Windows console cancellation consistently with submit, cancelling the owned media runner and unwinding cleanup.
- Use a plain Symfony Command without an Echo client factory. Text returns the absolute path, JSON returns {"output":"..."}; diagnostics use raw stderr.
- Update the existing maintainer skill with non-interactive `gh issue create --body-file` and require checking the returned issue through `gh issue view`. The observed UI form failure provides the baseline for this rule.

## Risks / Trade-offs

- Existing preparation limits apply, including the 500 MiB audio bound and supported precision/timeline restrictions; document them rather than silently widening the pipeline.
- Persistence needs space for a second audio copy in the input filesystem. Copy failure cleans staging; existing files are never opened for writing.
- Hard links require filesystem support (NTFS on Windows); failure is explicit and safe. Native media acceptance covers available platform tools; unit tests cover publication independently of FFmpeg.
- Forced termination or power loss cannot guarantee cleanup, matching existing submit semantics.
