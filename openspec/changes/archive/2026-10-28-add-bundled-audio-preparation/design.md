# Design

## Context

See `proposal.md` for motivation and the linked research. This design is based on `origin/dev` at `7f41160`, including the standalone packager and the #57/#58 installers. `SubmitCommand` currently calls the SDK with the original paths; `FileUploader` checks those paths against the MIME allowlist and 500 MiB size limit before upload. No component extracts audio.

The main Docker context currently excludes all of `build/`. `build/release_assets.py`, both installers and the standalone smoke harness allow only the current single-executable archive payload. The smoke harness copies only the CLI into its isolated working directory. These are required integration points, not incidental documentation changes.

Existing active changes own general standalone distribution, installation, Docker and polling behavior. Only `cli-connection-config` is canonical today, so the proposal adds two media-specific capabilities and does not edit another change's artifacts or archive it on their behalf.

## Goals / Non-Goals

**Goals:** a complete offline-capable local media toolkit in one default distribution; automatic video-to-audio preparation before the existing upload; literal and Unicode-safe paths; coherent installation/rollback; unchanged SDK service API and result shapes; reproducible build inputs and evidence for every supported target.

**Non-goals:** a general video converter, video encoders/decoders, remote URL downloads, runtime binary downloads, an FFmpeg PHP extension, browser/WASM processing, new signing certificates/notarization, a new release tag as part of proposal preparation, or a claim that media preparation reduces transcription billing.

## Decisions

### 1. Build one compact source profile across native targets

Use a reviewed FFmpeg release source archive, initially targeting the supported 9.0.2 patch release rather than the research prototype's 9.0.1. Record the actual SHA-256 only after upstream signature/source verification in the implementation task. This is an input-verification prerequisite, not a placeholder digest accepted by the build.

Add `build/media/config.json`, source verification/build helpers and a capability acceptance script. Keep common source identity, profile and flags in that one configuration. Record target-specific compiler, architecture, minimum OS and configure details separately. Disable external-library autodetection and all components by default; enable only the supported container/audio capabilities. Cache by source hash, common profile, target, compiler/base image and build-helper inputs; reject cached output whose manifest or hashes do not match. The cache is not an alternate source of provenance.

Start from the research profile: MOV/MP4, Matroska/WebM, AVI, MPEG, ASF and supported audio demuxers; AAC, Opus, Vorbis, MP3, FLAC, ALAC, AC3/EAC3, WMA and documented PCM audio decoders; FLAC/PCM output and copy muxers. The committed matrix must distinguish a container that can be inspected from a codec that can be converted. Unsupported audio remains an explicit error. Add only the audio filters required for timestamp normalization and silence insertion; prove their operation with fixtures. No video codecs, ffplay, capture devices or network protocols are needed.

Linux builds use musl and no interpreter/DT_NEEDED entries. macOS links FFmpeg's libraries statically, allows only system dylibs/frameworks and signs final binaries; use the same documented minimum macOS version as the CLI package, verified on the acceptance target. Windows uses native MinGW-w64/UCRT64 tools on the existing Windows runner and validates PE imports; UCRT/system DLLs must be supplied by the documented OS, not assumed from the build runner. Existing PHP runtime linkage checks remain in place and also inspect both media binaries.

Alternative: `apk add ffmpeg` is simpler to maintain, but the measured arm64 candidate added 102 packages and a 45.34 MiB compressed layer. The compact prototype's executable-only layer was 4.02 MiB. Universal third-party bundles also leave different suppliers/platform support and glibc requirements to reconcile. The selected approach accepts build ownership to keep one controlled profile.

### 2. Ship source/provenance materials with the complete package

The portable payload adds:

```text
libexec/ffmpeg[.exe]
libexec/ffprobe[.exe]
media-build-info.json
media-licenses/...
media-source.tar.xz
```

`media-build-info.json` records upstream version and source hash, common profile hash, target, effective flags, compiler/toolchain identity and minimum OS, source-package hash and hashes of both binaries. Preserve the corresponding source archive, any patches and build recipe inside `media-source.tar.xz`; include applicable compiler/libc notices. Configuration acceptance verifies LGPL-only enabled components and rejects GPL/nonfree drift. Running the toolkit as an external process does not make it a mandatory Composer/library dependency.

Including compressed corresponding sources avoids an extra source-hosting service, a dependency on mutable third-party URLs, and missing-source problems for branch Docker images published before a release tag. It adds compressed source bytes to each package. The research's binary-only 2.90/3.84 MiB standalone archives and 4.02 MiB Docker layer therefore are not final distribution-size promises. Record full before/after deltas and start with a 25 MiB compressed media-addition budget per target; fail the budget gate rather than silently substituting the full distro package. Any future move to separately hosted source assets needs its own availability/licensing and asset-contract review.

Keep the four existing release archive names and SHA256SUMS entries. Extend archive allowlists to these exact new members/subtrees, reject links and duplicate/case-colliding paths, verify binary/source-package hashes and set Unix executable bits on all three executables. Keep source archives compressed; do not copy a compiler or an expanded source/build tree into a runtime image.

### 3. Reuse media builds in Docker and standalone

Add a dedicated media build stage driven by `build/media/` to the Docker build. Copy its accepted binaries and materials to an image-owned directory such as `/opt/rarus-echo/media`, and let the CLI resolve that explicit location. Include only the required `build/media` inputs in `.dockerignore`; keep secrets, vendor, downloads, worktrees and unrelated build artifacts excluded.

Standalone `build/package.py` stages the accepted target toolkit next to the CLI. Linux standalone and Docker use the same profile/source inputs, even if their enclosing build stages differ. The standalone-specific Docker context must also include only the intended media fixtures/helpers and pins. Build and acceptance scripts must not depend on files from the original research `/tmp` directory or the developer's Homebrew install.

Extend Docker workflow path filters to media inputs and fixtures. Build and accept both image architectures before the multi-architecture publication step; do not push a candidate image before its tests. Extend the native standalone/installer matrix to accept media payloads on all four targets. A changed profile must invalidate both Docker and standalone media caches.

### 4. Preserve the native executable entrypoint with versioned installed packages

Keep the existing `rarus-echo[.exe]` entrypoint name and avoid introducing a shell/PowerShell/native launcher dependency. A portable extraction uses its adjacent `libexec/` and media metadata. For installation, stage the full verified archive into an installer-owned directory under `<install-dir>/.rarus-echo/packages/<version>-<full-source-sha>/`, then atomically replace the command in `<install-dir>` with the matching native CLI executable. The source commit determines the media pins, so each installed executable can resolve only its own versioned package from its embedded identity.

The toolkit locator accepts the portable adjacent bundle or this deterministic installed package location; it checks the common media identity and hashes before invoking the executables. It never treats cwd or the Windows temporary PHAR directory as the native executable directory. Establish that physical executable location before entering the PHAR, including the Windows micro extraction workaround; add runtime-probe acceptance for the chosen mechanism. Do not assume that `PHP_BINARY` or `__DIR__` points outside PHP micro/PHAR. A package with invalid adjacent metadata fails explicitly rather than selecting a different PATH binary.

Installers validate candidate CLI identity and both tool identities/fixtures before activation. Do not overwrite a pre-existing immutable package with a conflicting manifest. Retain previous versioned payloads so a process started before a Unix update can keep using its matching toolkit. Automatic garbage collection is excluded from this change; document the retained directories and safe manual removal after commands finish.

Windows may refuse replacement of an in-use CLI executable. Treat that as a failed update with an actionable diagnostic and the previous complete installation intact. This is preferable to adding a permanent native shim solely for concurrent updates. An in-use media binary is in its previous version's directory and is never overwritten. Existing rollback handling must encompass the executable, newly staged package and activation/notices changes; retain recovery files if rollback itself fails. Preserve the prior install's connection config, user PATH options and unrelated files.

The install remains user-scoped, supports current custom directories and Unicode paths, and keeps working after changing cwd. Existing legacy notice storage is migrated only within installer-owned destinations after successful activation; it must not become a reason to delete arbitrary files. The complete package is required for video; copying the bare executable continues to support commands that do not need media tools.

### 5. Keep media orchestration in the CLI

Create a focused internal `src/Infrastructure/Console/Media/` area:

| Unit | Responsibility |
| --- | --- |
| Toolkit locator | Resolve portable/installed/Docker or explicit Composer tool locations and validate provenance |
| Process runner | Literal argv, filtered environment, deadline/cancellation, bounded output and exit status |
| Probe model and stream selector | Parse bounded JSON, classify real video/audio/artwork and select a stream |
| Preparation policy | Select verified copy or lossless conversion and timeline normalization |
| Prepared file set | Ordered original/prepared associations and explicit temporary-file ownership |
| Preparation coordinator | Validate/prepare the complete batch and release resources around SDK submission |

Use strict types and immutable probe/options/result data. Keep cleanup ownership explicit rather than relying on a destructor. `SubmitCommand` validates existing wait/transcription options, then media options, resolves connection configuration without making a transcription request, prepares the whole batch and calls the existing client once with prepared paths. A `finally` block releases prepared files after SDK upload streams close, before polling begins. Public `Transcription::submit()`, transport interfaces and result objects retain their signatures.

Current supported, unambiguous audio MIME types retain the original path without invoking tools. Candidates with video/ambiguous media MIME are inspected; audio-only containers and attached cover art are not converted just because of their container or extension. Existing `FileValidator` remains authoritative for what is sent to Echo. Separate readable/non-empty source checks from its upload-size rule so a source video over 500 MiB can produce audio that passes the existing 500 MiB upload limit. Direct SDK callers and explicit direct-upload CLI mode retain their existing validation.

For Composer/custom installations, allow explicit `RARUS_ECHO_FFMPEG_PATH` and `RARUS_ECHO_FFPROBE_PATH` only as a pair of absolute paths, with version/capability validation against the documented profile. Do not search cwd or install packages. These variables configure local tools, not connection configuration; child environments exclude all `RARUS_ECHO_*` values. Native bundled paths are the default, and no FFmpeg package/extension is added to Composer requirements. Document the override as optional, with clear failures when required capabilities are unavailable.

### 6. Fix the user-visible preparation controls

| Control | Contract |
| --- | --- |
| Default `submit <files>...` | Prepare supported video audio; retain ordinary audio inputs |
| `--audio-stream=<index>` | Absolute ffprobe stream index; non-negative integer; one input that actually has video; reject missing/non-audio indices |
| `--no-extract-audio` | Explicit original-upload path under existing SDK validation; incompatible with `--audio-stream`; never entered as fallback |
| `--media-timeout=<seconds>` | Positive integer, 600 seconds by default, per input for combined probe/preparation; independent of HTTP/polling timeouts |

Pick the only default audio stream, otherwise the lowest absolute audio index; identify it on stderr. Do not infer it from `--language`. Do not add per-file map syntax in this first change: batches use automatic selection; a manual override is intentionally single-file to avoid ambiguity.

Prepare all inputs before the single batch upload. Preserve argument order and the association with returned file ids; a failure in a later file cleans earlier temporary outputs and makes no transcription request. Existing raw/output single-file restrictions and positive polling-number validation still run first. Preserve existing result shapes instead of adding media metadata to stdout.

### 7. Gate the format and timeline matrix on Echo evidence

Store the media compatibility matrix and dated evidence with the change/build fixture documentation. Candidate copy routes are AAC to M4A, Opus/Vorbis to Ogg, MP3 to MP3 and FLAC to FLAC. FLAC is the selected lossless fallback candidate; no default route is enabled solely because local FFmpeg or the public OpenAPI accepts a binary upload. The first implementation task verifies fallback service acceptance and the required timeline behavior with small non-sensitive fixtures; enable a copy route only when its separate evidence passes. A rejected copy candidate falls back to verified FLAC, not to uploading video. If the fallback/timeline baseline fails or cannot be checked, automatic preparation remains blocked for release and the issue stays open; do not substitute an unreviewed lossy format.

Copy only when the chosen codec/container and decoded timeline are verified compatible. Conversion preserves channel count and sample rate; no automatic mono/16 kHz policy. For a positive audio offset relative to the container playback origin, normalize to a zero-based audio file with actual leading silence via the verified lossless route, rather than relying on Echo to honor container edit-list offsets. Do not trim speech or rebase it silently. Ambiguous/missing timing or an unrepresentable timeline is a pre-upload error. Validate the decoded signal's placement within 50 ms, including leading silence, priming and representative offset fixtures; this is not a promise about ASR word-timestamp accuracy.

Acceptance against a local mock checks multipart bytes, selected stream and protocol integration; it does not satisfy the Echo format gate. Live tests use dedicated small fixtures and avoid logging credentials. Record tested service URL/date, case, returned status and actual output properties; retain only non-sensitive evidence. Full corpus includes rate/channel preservation and timestamped recognition modes.

### 8. Bound processes and temporary output without truncation

Use direct process argument arrays and absolute tool/file paths. Filter inherited credentials out of the child environment. Probe output and diagnostic logs are bounded; media-tool output never reaches CLI stdout. The runner must avoid Windows pipe deadlocks, for example by using private bounded output files with polling rather than assuming Unix nonblocking pipes.

Use private unpredictable temporary directories with restrictive permissions/ACLs. Enforce the configured deadline across probing and conversion per input, the existing 500 MiB prepared-file upload limit, a 1 GiB aggregate temporary-output budget per invocation, and available-disk checks while preparing. Monitor and terminate on overflow; do not use a silently truncating FFmpeg size option as successful conversion. Verify process success, output probe, stream count/codec/rate/channels/duration and final size before upload. A run can pass only if the complete audio is prepared.

Unix SIGINT/SIGTERM handling must stop the active child before returning the existing signal-aware failure. On Windows verify native PHP micro console-control handling and process-group termination with the actual packaged runtime; include any required narrow runtime bridge/patch in the pinned runtime inputs and native acceptance rather than claiming that Unix pcntl handles Windows. Normal completion, conversion errors, upload failures, supported cancellation and deadlines release owned files; cleanup retries must handle Windows file locks. Uncatchable termination/power loss is outside immediate-cleanup guarantees. No persistent media cache or arbitrary temporary-directory sweep is introduced.

### 9. Validate complete distributions, not host installations

Unit tests cover pure selection/policy/validation and runner/coordinator failure paths with fake clients/processes. Real-media acceptance uses committed small, licensed fixtures with expected packet hashes and decoded alignment. Fixture generation tools may be separate from the compact runtime; the accepted runtime itself must not need video encoders to generate tests. No test depends on `/opt/homebrew`, `/tmp` research files, a network download during CLI execution or a preinstalled FFmpeg.

The extracted-package harness copies the complete bundle into an isolated Unicode directory, empties PATH, changes cwd and tests video preparation plus a controlled local upload. Installer tests use the complete archive manifest and cover legacy migration, custom directory, repeated install, rollback, locked Windows exe, toolkit tampering and old/new package association. Keep live API tests separate and clearly label missing evidence as an unmet release gate.

## Risks / Trade-offs

- Own media builds add maintenance and platform work -> one manifest/profile, keyed cache and native acceptance on every supported platform; no extrapolation from arm64 prototypes.
- Codec/container support is deliberately bounded -> documented matrix and deterministic failure for unsupported inputs; no hidden full-package or original-video fallback.
- Source and license materials add download size -> measure the full package, not only executable bytes; keep source compressed and enforce the media-addition budget.
- Existing installer/archive guards reject sidecars -> update packager, both installers, smoke harness and path-filter gates together before publication.
- Windows locks and cancellation differ from Unix -> immutable versioned installed payloads, failure-safe executable replacement and packaged-runtime cancellation tests.
- Channel/rate/offset changes could affect diarization or timestamps -> preserve signal properties, prefer verified packet copy and use lossless normalization only with evidence.
- Batch staging can fill disk -> explicit per-file/aggregate budgets, timeouts, bounded logs and cleanup before polling.
- Changing physical binary-location logic can break PHP micro -> establish location before PHAR entry, test Windows temporary-PHAR behavior, clean PATH and different cwd.
- Another change may add CLI diagnostics such as #61 -> keep media location/capability checks in reusable internal units without expanding this issue into a doctor-command redesign.

## Migration Plan

1. Review this proposal/design and validate the OpenSpec artifacts. The initial change only adds planning artifacts and its CHANGELOG entry; all implementation tasks remain unchecked.
2. Confirm Echo fallback/timeline evidence, pin reviewed FFmpeg source and add the accepted media toolchain.
3. Extend complete-package Docker/standalone/installer delivery and its gates before enabling automatic preparation. Distribution-only commits are preparatory and do not close #60.
4. Implement CLI orchestration and its test matrix, then update README, build documentation, CLI help/reference and CHANGELOG to describe actual behavior.
5. Validate local gates, native package/image/installer checks and live compatibility evidence; open/update the implementation PR against `dev`, process agent review threads and required CI.
6. Merge only after those gates; archive this OpenSpec change only after the linked implementation PR has merged. Release/image publication follows the repository workflows and does not create an ad-hoc release tag during planning.

Rollback restores the previous native entrypoint and its retained versioned payload. CLI callers can explicitly use `--no-extract-audio` for the previous upload behavior when their original files satisfy existing upload rules. Removing the media feature must not rewrite connection files or remove unrelated user data.

### Verified fallback precision

The pinned non-experimental FFmpeg FLAC encoder preserves integer precision up to 24 bits. Fallback selects explicit 16-bit or 24-bit output and validates the resulting precision as well as channels/rate. Source floating-point PCM and lossless integer audio above 24 bits or with unknown effective depth are rejected before upload when conversion is required. Encoded copy routes preserve their packets without this conversion. Floating-point decoder output from a lossy codec is distinguished from source floating-point PCM and uses the service-verified 24-bit fallback.

### Integration with merged doctor and published releases

The merged doctor command uses the same strict toolkit resolution as submit, including the matching complete bundle or an explicit absolute override pair, within its existing five-second media-check deadline. Its independent configuration/connection checks, JSON schema and safe diagnostic code family remain intact. The #60 diagnostic delta supersedes the earlier PATH-only requirement in the already merged #61 change; that separate change is not archived here.

The only published standalone archive predating media is tag `0.5.0-rc-1`, source `e359975527bd483707dc39d23599545a77c1620d`. A frozen exception preserves installation and public-main-installer acceptance for precisely that version/source pair and the exact old archive shape, without any media members. Its old CLI has no automatic media preparation. Unknown versions/sources and all new builds retain mandatory complete media validation; absence of a media file never triggers legacy fallback. The exception retains whole-archive checksums, native executable identity, modes, notices, rollback and repeat-install checks. Historical acceptance continues through the public main installers.
