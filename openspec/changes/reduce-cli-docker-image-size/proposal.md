## Why

Issue #25 reports that the published CLI image `ghcr.io/mesilov/rarus-echo-php-sdk:cli` is larger than expected for a command-line SDK wrapper. The measured baseline is about `181MB` compressed pull size and `769MB` displayed local size, and `docker history` shows the size is dominated by the `php:8.4-cli-bookworm` runtime layers (`/usr` is `531MB` while the SDK application layer `/app` is only `18MB`). Optimizing `/app` alone cannot meaningfully reduce the image, so the runtime base must change.

## What Changes

- Switch the CLI Docker vendor and runtime stages from `php:8.4-cli-bookworm` to the official `php:8.4-cli-alpine` base image.
- Replace Debian `apt-get` package installation with Alpine `apk` for build tooling (`git`, `unzip`) and the runtime CA bundle (`ca-certificates`).
- Keep the existing multi-stage Composer/vendor build pattern, the PSR-17/PSR-18 discovery build smoke check, the `rarus-echo` entrypoint, the CLI PHP runtime limits, and the required `curl`, `fileinfo`, and `mbstring` extensions.
- Preserve multi-arch publication for `linux/amd64` and `linux/arm64`.
- Capture before/after size metrics and document the new runtime base and expected size in the README and changelog.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `cli-docker-image`: The self-contained CLI Docker image is built on an optimized official PHP Alpine runtime base to reduce published image size while preserving CLI runtime behavior.

## Impact

- Affected Docker artifacts: `docker/rarus-echo-cli/Dockerfile`.
- Affected documentation: README Docker CLI section and `CHANGELOG.md`.
- Affected CI: none. The existing `.github/workflows/docker-build.yml` multi-arch build inputs and publication behavior are unchanged.
- Affected runtime SDK API: none. This change does not touch PHP source or public SDK behavior.
- Runtime base change: the published image switches from glibc/Debian to musl/Alpine, so TLS, curl, fileinfo, and mbstring behavior is verified with build-time discovery checks and live-API smoke tests before publication.
