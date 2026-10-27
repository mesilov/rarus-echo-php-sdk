# Implementation verification

Dates: 2026-10-27–28 (native CI timestamps are UTC). Scope: approved #60 implementation, including automatic CLI audio preparation and compact native FFmpeg/ffprobe distribution. The OpenSpec remains active until its implementation PR merges.

## Accepted implementation

- Pinned signed FFmpeg 9.0.2 archive: SHA256 `8c3850283eb25fa026482078a04051e0be17347b09ef81a0849bec15a96e002e`; common profile SHA256 `cdf603195eb41ae10572d8a66efe763c5699d6e5d60838fb802d2b8758e5a760`. Both binaries ship matching provenance, hashes, notices and compressed corresponding sources/recipe.
- `submit` prepares the ordered batch before one upload, supports absolute `--audio-stream`, explicit `--no-extract-audio` and independent `--media-timeout=600`, and cleans owned output before polling. Ordinary audio and public SDK contracts retain their existing path.
- Codec copy routes are restricted to the dated service acceptance matrix. Other supported input audio uses lossless FLAC; playback offsets become actual leading silence. Local decoded-signal checks use 50 ms tolerance; no ASR timestamp-accuracy claim is made.
- Subprocesses use argv arrays, filtered environments, bounded output, deadlines and explicit cancellation. Temporary storage is private, prepared files are limited to 500 MiB each and 1 GiB per invocation, and decoded frame listings are bounded to 64 MiB/1,000,000 frames.
- Installers stage immutable version/source packages before entrypoint activation. Rollback intents precede filesystem/PATH mutations. POSIX tests deliver TERM immediately after successful moves and again during rollback; PowerShell tests inject failures immediately after successful native activation operations.
- Native package smoke and installer acceptance run with empty PATH, Unicode paths and another cwd, exercising actual video-to-audio multipart upload. Native cancellation acceptance interrupts an active bundled FFmpeg and checks child exit/output cleanup. A separate native timeout gate proves the child is active before its six-second deadline and checks termination/cleanup; both checks pass in the native packaged runtime on all four supported targets, including real Windows CTRL_BREAK and process-handle exit checks. No extra micro console initialization patch is necessary: the pinned PHP core already initializes it for the CLI-compatible micro runtime.

## Local evidence

The branch integrates `dev` at `1a00cb9`, retaining doctor, raw Windows user PATH/value-kind restoration, Unicode TEMP bootstrap and published-release acceptance. Doctor uses the same toolkit as submit within its original five-second total budget and JSON contract. The frozen published RC exception is explicit by exact version/source; new packages always require complete media.

- `make lint-openspec`, `git diff --check` and `make lint-all` pass, including generated CLI-reference validation, PHP CS Fixer, PHPStan and Rector. Dependency deprecation notices do not change the successful exit status.
- `make test-unit`: 353 tests, 1040 assertions, three explicitly opt-in corpus cases skipped in the default suite and executed separately.
- Explicit real compact-tool media and diagnostics suites: 94 tests, 361 assertions, no skips. Coverage includes packet identity, channels/rate, delayed signal alignment, audio-only/artwork, stream selection, corruption/no-audio, resources and cancellation.
- `python3 -m unittest discover -s build/tests -v`: 54 passing tests. They cover canonical LF metadata under Windows autocrlf, provenance/cache/source contracts, archive safety/modes/checksums, layer accounting, Unicode logging under forced CP1252, exact owned-process identity and real inherited-pipe teardown.
- Local POSIX acceptance also passed with the actual published macOS `0.5.0-rc-1` archive (SHA256 `e773183ea3d9f872cdeb8fce632373225b47bd610cf9b82ec78992d44632f167`), including repeat/custom Unicode installs and unchanged connection configuration.
- Live Echo acceptance repeated with the final compact FFmpeg 9.0.2 profile: 8/8 completed synthetic-speech cases, including stereo 48 kHz 24-bit FLAC. Named apple/orange markers shifted by exactly +2 seconds for 32,000 samples of real leading silence at 16 kHz. Dated non-sensitive evidence is in `tests/Fixtures/Media/echo-acceptance.json`, hashed by the compatibility matrix; synthesized voice files and credentials remain untracked.

## Native CI evidence

All six workflows pass on acceptance commit `ddb5b91de0294ad096b087920c40f5e5b9c38be6` in [PR #67](https://github.com/mesilov/rarus-echo-php-sdk/pull/67): [Tests](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40557735261), [Lint](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40557735345), [CLI installers](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40557735458), [public-release contracts](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40557735344), [Docker](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40557735472) and [standalone](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40557735362). Publication jobs are intentionally skipped for PR events.

- All four standalone targets pass native toolkit/capability/corpus/linkage, complete source/license payload, isolated executable/doctor, active-child cancellation and six-second timeout checks. Windows uses native UCRT64 and checked system DLLs; macOS tools have final signatures/system-only linkage; Linux uses static musl. Runners are Ubuntu 24.04 amd64/arm64, macOS 15 arm64 and Windows Server 2022 amd64; the documented Windows desktop floor remains Windows 11.
- Each POSIX target passes all 23 installer tests with its real new archive and no skips. Native Windows PowerShell 5.1 passes all 68 tests with the real archive; offline tests and PowerShell 7 Unicode bootstrap also pass. Default and Unicode custom installs, repeat activation, migration, in-use executable failures and fault-injected rollback preserve the previous package/configuration.
- Each installed new package performs one ordered two-video multipart submission to a controlled local HTTP server with empty PATH and a different cwd. Received files are audio-only AAC/FLAC with preserved channels/rate; original bytes and clean private temporary storage are checked. Linux archives repeat smoke in a PHP-free container. Aggregate release-archive identity/hash validation passes for all four artifacts.
- Both native Docker targets pass offline probe/copy/transcode and controlled upload with read-only source inputs. Image acceptance inspects the final filesystem and complete source/notices overhead against the named same-runtime `cli-baseline`.

Complete standalone additions measured by the `media-size-*` JSON artifacts from that run follow. Baseline: `7f41160` archive layout with the same CLI/runtime and media omitted. All additions are below 26,214,400 B (25 MiB).

| Target | Baseline archive B | Complete archive B | Compressed media addition B | Uncompressed media B |
| --- | ---: | ---: | ---: | ---: |
| linux-amd64 | 24,404,137 | 40,215,585 | 15,811,448 | 19,402,424 |
| linux-arm64 | 24,671,081 | 40,436,048 | 15,764,967 | 18,788,921 |
| macos-arm64 | 25,205,614 | 40,434,999 | 15,229,385 | 18,272,552 |
| windows-amd64 | 25,098,457 | 40,853,094 | 15,754,637 | 19,490,550 |

Docker reports use decoded saved-layer bytes, preserving engine `.Size` separately because containerd may report compressed blobs. Complete gzip media layers include both tools, metadata, notices and corresponding sources, and each remains below the same 25 MiB gate.

| Target | Baseline decoded B | Complete decoded B | Decoded addition B | Complete gzip media B |
| --- | ---: | ---: | ---: | ---: |
| linux-amd64 | 125,453,312 | 145,965,056 | 20,511,744 | 16,214,189 |
| linux-arm64 | 120,079,872 | 139,929,600 | 19,849,728 | 16,267,789 |

## Review and CI regressions

Independent spec/delivery reviews have no remaining actionable findings. Fixes include exact 16/24-bit PCM equality and rejection of unrepresentable PCM32/float precision, attempt-all owned-file cleanup preserving primary errors, and post-move TERM/activation rollback. The original TERM reproducer exits 143, preserves the prior CLI and removes only the incomplete new package. PR agent comments and inline threads were checked; the bot reported no findings and there were no unresolved threads.

Actual Windows CI additionally verifies genuine-null atomic rollback, raw `REG_SZ` versus expandable `REG_EXPAND_SZ` PATH preservation, exact frozen historical ZIP producer modes, and strict unambiguous JSON keys. Build/acceptance Python output uses UTF-8 under CP1252. Manifest writers emit canonical UTF-8/LF and raw validation rejects CRLF before packaging. Real installer conversion uses 1024 s16le frames; regression tests enforce the valid minimum rather than that exact chosen size. Cancellation selects the exact owned bundled executable while retaining console-host diagnostics; bounded failure teardown reaps the owned tree without masking the original error. Target cache fallbacks retain the full PHP runtime-input guard and media source/profile/compiler/recipe validation; Linux cache scopes are stable per target.

## Environment and merge lifecycle

Local Docker could not allocate another worktree network (`all predefined address pools have been fully subnetted`). Successful local PHP checks used a temporary Compose override with the existing dev image and external `rarus-echo-php-sdk_rarus-echo-network`; repository Compose configuration and existing networks were preserved.

Implementation and acceptance tasks are complete. #60 and the OpenSpec remain open until PR #67 merges; this session publishes no release or registry image. The modified diagnostic delta depends on archiving the separate already merged `add-cli-doctor` change before archiving this change so the canonical diagnostic spec exists. No unrelated OpenSpec change is archived here.
