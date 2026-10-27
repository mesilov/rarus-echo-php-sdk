# Release verification

Date: 2026-10-28 (Asia/Bishkek). Issue: #69. Target: exact prerelease 0.5.0-rc-2.

## Prepared source

PR #67 merged verified head 54008876e3e2028ca60fdefa8289d1fcf6b91479 into dev as e46d96962709a36de1c072be7bacb67dc20ebc72. All six feature workflows were green and no unresolved agent threads remained. The release worktree is based on that dev merge; the separate stable documentation branch for #68 was preserved.

CHANGELOG rolls only actual Unreleased changes into rc-2, leaving an empty Unreleased and preserving rc-1. README uses exact rc-2 Composer/installer commands and doctor availability; the historical rc-1 media exception remains exact. Merged doctor/media OpenSpec changes are archived in dependency order with synchronized canonical specs. Diagnostic scenario titles retain their previous identities with the approved new toolkit semantics. Independent review found the former hardcoded rc-1 documentation requirement; the release delta now selects the current RC while preserving historical notes/legacy identity. The initial preparation commit changed no runtime or CI code. PR #70 subsequently reproduced the existing audio-only WebM contract violation described below; the focused CLI fix implements that approved behavior, with no dependency, media-profile or CI change.

## Local gates

- make lint-openspec: 25 items passed; no missing diagnostic canonical dependency remains.
- make test-unit after the WebM fix: 392 tests, 1088 assertions; three opt-in real-corpus tests skipped by the standard suite and executed separately. Product media/native evidence is retained with archived #60.
- make lint-all: generated CLI reference, OpenSpec, PHP CS Fixer, PHPStan and Rector passed; dependency deprecation notices are nonfatal.
- python3 -m unittest discover -s build/tests -v: 54 passing contracts.
- Explicit real compact-tool media and diagnostics suites: 133 tests, 409 assertions, no skips; the earlier accepted Linux arm64 toolkit was mounted read-only.
- git diff --check: passed.

Local Docker checks reuse the existing project dev image and external network with a temporary Compose override because the host's default address pools are exhausted. Credentials were not read or printed.

## PR review follow-up

PR #70 bot finding PRRT_kwDOQieu5c6w1QhY / comment 4392735956 was verified with the real fileinfo result: audio-only.webm and video-opus.webm both report video/webm, while audio-only.m4a reports audio/x-m4a. The audio-only fixture reproduced ToolkitUnavailableException without overrides. Bounded structural WebM inspection proves audio-only tracks before toolkit resolution, preserving the original path/bytes and stream-selection rejection while video/uncertain containers retain verified preparation. Focused Media suite: 75 tests / 134 assertions (two existing opt-in corpus cases skipped there and executed in the explicit real-tool run). Independent re-review approved the frozen fix. Metadata reads are limited to 1 MiB and 4096 headers; binary payloads are skipped by finite element boundaries, and only root Segment unknown size is bounded by EOF. Tests cover the actual audio/video fixtures, original byte identity, manual selection, unknown-root variants, duplicate Tracks, video/unknown entries, malformed/truncated tails, concatenated documents and resource limits.

The first Windows PR build failed during FFmpeg HTTPS source acquisition with an SSL handshake timeout before compilation. A cache miss preceded that fresh source fetch; the final source must still pass its own native build. No TLS or checksum checks are bypassed.

## Publication evidence

Release PR #70 merged verified head 63a0ca8d70927a40c9b465de8fe49d5ebd160da7 after all six workflows passed, including native matrix run 40564546109. The exact main merge SHA is ba667107bb7611cbc0f3f47859cb63e970a635b9; its tree equals the reviewed source. Lightweight tag 0.5.0-rc-2 points to that exact main commit. Tag publication workflow 40568365590 is running. Native publication, public-asset acceptance and macOS checksum/handoff are recorded at closeout after they succeed. The active tasks deliberately keep these gates open before publication.
