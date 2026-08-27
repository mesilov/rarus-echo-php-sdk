## Why

Issue #10 asks for a Docker image build pipeline for the CLI app. The SDK now exposes `bin/rarus-echo`, so maintainers need an automated, reproducible way to build and publish a container image that runs the CLI without requiring consumers to install PHP and Composer locally.

## What Changes

- Add a production CLI Dockerfile that installs runtime dependencies, vendor packages, SDK source, and exposes `rarus-echo` as the container entrypoint.
- Add a GitHub Actions Docker build workflow based on the linked b24phpsdk example, adapted for this repository and CLI image.
- Build the CLI image for `linux/amd64` and `linux/arm64`.
- Validate the image build on pull requests without publishing packages.
- Publish the image to GitHub Container Registry on `dev`/`main` pushes and manual workflow dispatch.
- Add a `.dockerignore` so local credentials, dependencies, caches, and generated artifacts are not sent in the Docker build context.
- Document the CLI Docker image usage.

## Capabilities

### New Capabilities

- `cli-docker-image`: Self-contained Docker image and GitHub Actions build pipeline for the RARUS Echo CLI.

### Modified Capabilities

- None.

## Impact

- Affected CI: new `.github/workflows/docker-build.yml`.
- Affected Docker artifacts: new `docker/rarus-echo-cli/Dockerfile` and `.dockerignore`.
- Affected documentation: README Docker usage and changelog notes.
- Affected runtime SDK API: none.
