# Implementation verification

Date: 2026-10-27. Scope: approved #60 implementation, including automatic CLI audio preparation and compact native FFmpeg/ffprobe distribution. The OpenSpec remains active until its implementation PR merges.

## Accepted implementation

- Pinned signed FFmpeg 9.0.2 archive: SHA256 `8c3850283eb25fa026482078a04051e0be17347b09ef81a0849bec15a96e002e`; common profile SHA256 `cdf603195eb41ae10572d8a66efe763c5699d6e5d60838fb802d2b8758e5a760`. Both binaries ship matching provenance, hashes, notices and compressed corresponding sources/recipe.
- `submit` prepares the ordered batch before one upload, supports absolute `--audio-stream`, explicit `--no-extract-audio` and independent `--media-timeout=600`, and cleans owned output before polling. Ordinary audio and public SDK contracts retain their existing path.
- Codec copy routes are restricted to the dated service acceptance matrix. Other supported input audio uses lossless FLAC; playback offsets become actual leading silence. Local decoded-signal checks use 50 ms tolerance; no ASR timestamp-accuracy claim is made.
- Subprocesses use argv arrays, filtered environments, bounded output, deadlines and explicit cancellation. Temporary storage is private, prepared files are limited to 500 MiB each and 1 GiB per invocation, and decoded frame listings are bounded to 64 MiB/1,000,000 frames.
- Installers stage immutable version/source packages before entrypoint activation. Rollback intents precede filesystem/PATH mutations. POSIX tests deliver TERM immediately after successful moves and again during rollback; PowerShell tests inject failures immediately after successful native activation operations.
- Native package smoke and installer acceptance run with empty PATH, Unicode paths and another cwd, exercising actual video-to-audio multipart upload. Native cancellation acceptance interrupts an active bundled FFmpeg and checks child exit/output cleanup. No extra micro console initialization patch is necessary: the pinned PHP core already initializes it for the CLI-compatible micro runtime.

## Local evidence

- Final `make lint-openspec`, `git diff --check` and `make lint-all` pass. The latter includes generated CLI-reference validation, PHP CS Fixer, PHPStan and Rector; dependency deprecation notices do not change the successful exit status.

- `make test-unit`: 284 tests, 740 assertions, one explicitly opt-in real-media corpus test skipped by the default suite. The final suite includes regressions preserving primary errors on cleanup failure.
- Explicit real-media suite with `RARUS_ECHO_RUN_MEDIA_ACCEPTANCE=1` and the bundled Linux arm64 pair: 29 tests, 90 assertions, no skips. It covers packet identity, channel/rate preservation, delayed signal alignment, audio-only/artwork handling, deterministic track choice, corruption/no-audio and resource/cancellation behavior.
- `python3 -m unittest discover -s build/tests -v`: 20 tests passed, including provenance/source/cache validation, safe archive paths and collisions, complete checksums/executable modes and raw/gzip image-layer accounting.
- POSIX offline installer suite: 19 tests passed with one real native archive test pending platform builds. Default/custom Unicode paths, repeat installs, unchanged connection config, malicious payloads, post-move signals and rollback preservation are covered.
- Native macOS arm64 and static-musl Linux arm64 toolkits pass all committed 12-file corpus, capability, corresponding-source and linkage checks. These results do not substitute for Linux amd64 or Windows execution.
- Docker arm64 passes offline preparation with read-only inputs and an actual audio-only upload to a controlled local HTTP server. Against the named same-runtime `cli-baseline`, decoded layer bytes are 119,905,280 -> 139,754,496 (increment 19,849,216 B); the complete gzip media addition including sources/notices is 16,446,304 B, below 25 MiB. Engine `.Size` is retained separately because containerd may report compressed blobs.
- Live Echo acceptance was repeated with final compact FFmpeg 9.0.2 outputs: 7/7 successful completed transcription tasks. Named apple/orange markers shifted by exactly +2 seconds for 32,000 samples of real leading silence at 16 kHz. Non-sensitive dated evidence and final toolkit identity are in `tests/Fixtures/Media/echo-acceptance.json`; the compatibility matrix hashes that record. Synthesized voice files and credentials remain outside tracked files.
- Independent delivery review: all four earlier findings fixed and re-reviewed. The original post-move TERM reproducer now exits 143, preserves the prior CLI and removes only the incomplete new package; no remaining actionable delivery findings.

## Environment and final gates

Docker cannot allocate a new worktree network (`all predefined address pools have been fully subnetted`). Successful local PHP checks use a temporary Compose override selecting the existing project dev image and external `rarus-echo-php-sdk_rarus-echo-network`. Repository Compose configuration and existing networks were not modified or removed.

Windows PowerShell 5.1, native installed executable/cancellation acceptance on all four standalone targets, Linux amd64 toolkit execution, both Docker platform gates, final CI and PR agent comments remain required. They are not claimed from local arm64 or mocked installer tests. The workflows block publication until these native gates pass. No release or registry image has been published by this implementation session; #60 remains open pending these gates and merge.
