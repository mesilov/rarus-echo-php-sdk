# Tasks

The approved proposal, design and two spec deltas define the implementation. Checked tasks have recorded implementation evidence in `verification.md`; native platform gates remain open until their own execution succeeds.

## 1. Establish accepted media inputs and service outputs

- [x] 1.1 Record dated Echo acceptance of the FLAC fallback with small non-sensitive fixtures, preserving channels/sample rate and a known leading-audio offset; verify completed service results and timeline behavior, or record the release blocker without enabling automatic preparation.
- [x] 1.2 Record separate evidence for candidate AAC/M4A, Opus/Ogg, Vorbis/Ogg, MP3 and FLAC copy routes; verify each enabled entry in the committed compatibility matrix has a passing service case and that unsupported routes choose only the verified fallback.
- [x] 1.3 Add small licensed fixtures and expected properties/packet hashes under a focused media fixture directory, including real video, audio-only/artwork containers, two tracks, delayed audio, no audio, corruption and Unicode paths; verify the fixtures have documented provenance and deterministic local expectations.

## 2. Build the compact native toolkit

- [x] 2.1 Add `build/media/config.json` with a verified supported FFmpeg release/source hash, common capability profile and target inputs; add source/cache contract tests that reject a checksum mismatch, wrong target, stale profile and incomplete provenance, and verify them with the focused Python build-test suite.
- [x] 2.2 Implement bounded source acquisition/verification and native build helpers in `build/media/`; verify both binaries report the pinned release and accepted audio/container capabilities while network protocols, video codecs and GPL/nonfree autodetection remain disabled.
- [ ] 2.3 Build and verify Linux amd64/arm64 with musl, macOS arm64 with system-only linkage/final signatures and Windows amd64 with native MinGW-w64/UCRT64; verify all native linkage/capability/fixture checks and the documented OS floor rather than extrapolating results between targets.
- [x] 2.4 Produce `media-build-info.json`, binary/source-package hashes, `media-licenses/` and compressed corresponding `media-source.tar.xz`; verify an offline package contains the exact sources/patches/recipe and matching provenance for both shipped binaries.
- [ ] 2.5 Add complete toolkit size reporting and a 25 MiB compressed media-addition gate, including source/notices overhead; verify a named no-media baseline and each supported target's report, and document build/update operation in `build/README.md` and CONTRIBUTING.

## 3. Extend standalone package contracts

- [x] 3.1 Extend `build/release_assets.py` and its contract tests to allowlist the media payload and require valid identity/hashes, safe unique members and all Unix executable modes; verify `python3 -m unittest discover -s build/tests -v` rejects missing, tampered and wrong-target payloads while retaining four archive names and SHA256SUMS selection.
- [ ] 3.2 Stage the accepted toolkit from `build/package.py` and Linux standalone build stages; verify each extracted archive contains its native CLI, tools, provenance and source/notices without undeclared host dependencies.
- [ ] 3.3 Update `build/tests/smoke.py` to isolate the complete bundle, empty PATH and change cwd before probe/copy/transcode and controlled local multipart upload; verify successful audio-only submission from video and preservation of the source bytes on each target.
- [x] 3.4 Update standalone workflow/cache inputs and publication dependencies; verify a wrong toolkit identity or failed platform fixture/installer acceptance blocks publication and a media-only input change triggers the required builds.
- [ ] 3.5 Update standalone installation/build documentation for the complete package and bare-executable limitations; verify the documented extraction and native invocation work with a clean PATH and no external FFmpeg.

## 4. Install and roll back complete packages

- [x] 4.1 Extend POSIX installer archive guards and stage `<install-dir>/.rarus-echo/packages/<version>-<source-sha>/` before replacing the matching native entrypoint; verify offline installer tests cover legacy migration, repeat install, custom/Unicode directories, PATH opt-out and unchanged connection configuration.
- [ ] 4.2 Extend PowerShell archive guards and package activation with the same identity/layout policy; verify `build/tests/windows-installer-check.ps1` under Windows PowerShell 5.1 covers the complete toolkit and does not require extra host tools or administrator privileges.
- [ ] 4.3 Add complete-package rollback, wrong-binary/hash and activation-failure cases to both installer suites; verify the previous entrypoint, toolkit and notices remain usable together and incomplete rollback retains identified recovery files.
- [ ] 4.4 Test Windows in-use executable handling and retained old toolkit directories; verify a locked CLI replacement fails clearly without overwriting running tools or mixing installed versions, and document retained-package cleanup after commands finish.
- [ ] 4.5 Extend installer CI input filters and native acceptance to real media archives; verify changes to media manifest/packaging inputs exercise all supported installer targets and README/CONTRIBUTING paths match the installed layout.

## 5. Include media in the default Docker image

- [x] 5.1 Add a dedicated compact media build stage driven by `build/media/` and narrowly extend `.dockerignore`; verify no credentials/vendor/downloads/worktrees or unrelated artifacts enter the context and no general-purpose `apk add ffmpeg` dependency is introduced.
- [ ] 5.2 Copy accepted binaries, provenance, notices and compressed source materials into the runtime image; verify both linux/amd64 and linux/arm64 execute probe/copy/transcode with a read-only input mount and no compiler/expanded build tree in the final filesystem.
- [ ] 5.3 Split Docker candidate acceptance from multi-architecture publication and extend workflow/cache input filters; verify each platform's fixtures pass before push and record complete compressed/uncompressed image deltas against the pinned baseline.
- [x] 5.4 Update Docker build/use documentation for the bundled toolkit and temporary-storage needs; verify the documented one-command video workflow on the accepted image and preserve help/default entrypoint behavior without credentials.

## 6. Implement internal CLI media components

- [x] 6.1 Add strict internal toolkit/probe/options/prepared-file models in `src/Infrastructure/Console/Media/`; verify focused unit tests cover deterministic selection, absolute stream indices, cover art, ambiguous containers and disabled compatibility routes.
- [ ] 6.2 Capture the physical native executable/package location before PHAR entry and implement portable, installed and Docker toolkit resolution; verify isolated native tests cover Windows temporary-PHAR loading, different cwd, invalid adjacent metadata and both absolute Composer override paths without changing connection config.
- [x] 6.3 Implement direct argv process execution with filtered environment and bounded probe/diagnostic output; verify unit and real-process tests cover literal shell-like/Unicode paths, non-zero exits, malformed/oversized probe JSON and no inherited Echo credentials or stdout contamination.
- [ ] 6.4 Implement positive per-input deadlines and owned process termination on Unix and packaged Windows; verify native timeout/cancellation tests, including any required narrow PHP micro console-control bridge/patch, and do not mark Windows complete from pcntl tests.
- [x] 6.5 Implement verified copy/lossless conversion and playback-origin normalization; verify packet identity for copy, channels/rate preservation for conversion and at most 50 ms local decoded-signal misalignment with actual leading silence for offset fixtures.
- [x] 6.6 Enforce final upload-size, 1 GiB aggregate temporary-output and available-space limits without successful truncation; verify an over-500-MiB source video can prepare acceptable audio and oversized/partial outputs fail before upload.
- [ ] 6.7 Implement explicit prepared-file ownership and cleanup after preparation errors, timeout, cancellation and completed/failed SDK upload; verify private storage permissions/ACLs, Windows close/remove behavior and preservation of original/unrelated files.
- [x] 6.8 Document internal component boundaries, supported routes and resource policy in the relevant build/CLI contributor documentation; verify each documented behavior maps to a focused unit or native acceptance case.

## 7. Integrate automatic preparation into submit

- [x] 7.1 Add `--audio-stream`, `--no-extract-audio` and `--media-timeout` with early validation and default 600 seconds; verify command tests cover conflicting options, integer/range errors, single-video override restrictions and preservation of existing wait-option validation.
- [x] 7.2 Prepare the complete ordered batch before the single SDK submission call and release prepared files before polling; verify fake-client tests prove no upload after a later preparation failure and preserve source-to-file-id/result ordering for mixed audio/video batches.
- [x] 7.3 Retain ordinary audio, explicit original-upload mode, public SDK signatures and connection precedence; verify audio-only/SDK tests run without media tools and malformed toolkit errors do not trigger downloads or original-video fallback.
- [x] 7.4 Preserve human, JSON, raw-result and output-file contracts while routing preparation diagnostics to stderr; verify command tests assert clean stdout/output files and existing transcript options/headers for both wait and non-wait video submissions.
- [x] 7.5 Update command help, README, CHANGELOG, transcription-skill instructions and its CLI-reference generator allowlists; regenerate the reference and verify `make lint-agent-plugins` agrees with all three options, defaults and constraints.

## 8. Verify the integrated user story and review

- [x] 8.1 Run `make lint-openspec`, `git diff --check`, `make test-unit`, `make lint-all` and `python3 -m unittest discover -s build/tests -v`; verify all applicable local gates pass with the final implementation and record exact infrastructure blockers without treating them as passes.
- [ ] 8.2 Run the full native package/installer/Docker fixture matrix, long-file/resource/cancellation cases and isolated controlled-upload checks; verify all supported targets, complete-package sizes, source materials and legacy migration on the final inputs.
- [x] 8.3 Re-run required live Echo compatibility/timeline cases for the enabled routes against the final profile; record non-sensitive dated evidence and verify no unaccepted route is enabled or described as supported.
- [ ] 8.4 Open/update the implementation PR against `dev` with the final scope and validation; verify required CI and inline/top-level agent comments against current code, fix or explain each valid/obsolete comment and resolve only after disposition is supported.

## Workflow follow-up

- Keep #60 open while automatic preparation, required native/live acceptance, CI or agent review work remains. An OpenSpec-only planning commit/PR must reference #60 without auto-closing it.
- After the linked implementation PR has merged, archive `add-bundled-audio-preparation` with the repository workflow and validate the resulting canonical specs. Do not archive other active changes as part of this issue.
- Create a release tag or publish release assets only through the authorized release workflow; clean the issue worktree after merge when it no longer contains needed work.
