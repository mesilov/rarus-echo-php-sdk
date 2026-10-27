# Proposal

## Why

[Issue #60](https://github.com/mesilov/rarus-echo-php-sdk/issues/60) asks users to submit a local video in one command while the CLI sends only its audio to Echo. The [2026-10-27 research](https://github.com/mesilov/rarus-echo-php-sdk/issues/60#issuecomment-6443328995) and the user's selected approach establish that a small, source-built FFmpeg/ffprobe bundle can remove manual extraction without the size and runtime dependencies of a general-purpose media distribution.

## What Changes

- Build a pinned, compact FFmpeg/ffprobe profile for Linux amd64/arm64, macOS arm64 and Windows amd64; include provenance, capability manifests, licenses and corresponding source/build materials.
- Include the same Linux media profile in the default multi-architecture Docker image through a separate build stage, without installing the full Alpine FFmpeg dependency tree.
- Extend the existing standalone archives and installers to verify, install, locate, update and roll back the CLI and its media binaries as one package. Users still install one archive and do not install or download FFmpeg separately.
- Automatically prepare audio for video inputs in `submit`, select an audio stream deterministically, preserve the source's timeline, and upload only validated audio. Ordinary supported audio inputs keep their current path.
- Add optional `--audio-stream=<index>`, `--no-extract-audio` and `--media-timeout=<seconds>` controls while retaining existing submit/wait stdout, stderr, file-id ordering and connection configuration contracts.
- Make real Echo format/timeline acceptance and native acceptance on every supported target release prerequisites; research-only compatibility and size measurements are not production evidence.

This proposal records the implementation contract and its tasks. It does not by itself add binaries, change PHP behavior, publish images/releases, or close #60.

## Capabilities

### New Capabilities

- `cli-media-toolchain`: Source-verified compact media builds, bundled Docker/standalone delivery, coordinated installation and updates, and acceptance/publication gates for that bundle.
- `cli-media-preparation`: CLI-owned preparation of local video audio, stream/timeline policy, bounded processes and temporary files, and compatibility with existing upload and result modes.

### Modified Capabilities

- `cli-diagnostics`: The executable readiness check follows the same verified bundled/override toolkit resolution as video submission, retaining its five-second budget and report contract.

`cli-connection-config` remains unchanged. The existing active `standalone-cli`, `cli-installation`, `cli-docker-image`, `cli-app` and `cli-submit-wait-polling` deltas were inspected; these new media-specific capabilities extend those workflows rather than redefining their general requirements. The completed upstream changes retain their own archive lifecycle.

## Impact

Implementation changes the CLI's default handling of video and the contents/install layout of standalone packages. Moving only `rarus-echo[.exe]` out of its archive will no longer suffice for video preparation; moving/extracting the complete package will. `--no-extract-audio` explicitly preserves direct-upload behavior.

Affected areas: `build/`, `docker/rarus-echo-cli/`, `docker/standalone/`, `.dockerignore`, `install.sh`, `install.ps1`, native packaging/installer/Docker workflows, CLI submit orchestration and its internal media components, tests/fixtures, README, build documentation, CHANGELOG and the transcription skill's CLI reference. PHP runtime code will change during implementation, but the public SDK service signatures, transport contracts, result objects and Composer binary delivery remain intact. FFmpeg stays an external CLI process, not a mandatory PHP library dependency.

## Integration dependency

PR #63 merged the doctor implementation while #60 was in progress. The diagnostic delta in this change modifies its executable check to match the bundled runtime. Archive the already merged `add-cli-doctor` change before archiving #60 so the diagnostic canonical spec exists; this issue does not archive that separate change.
