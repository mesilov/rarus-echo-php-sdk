## 1. OpenSpec

- [x] 1.1 Read issue #10 and confirm scope, milestone, labels, comments, and linked example workflow.
- [x] 1.2 Check active OpenSpec changes for overlap.
- [x] 1.3 Add OpenSpec proposal, design notes, spec delta, and task checklist for the Docker image build pipeline.

## 2. Docker Image

- [x] 2.1 Add a failing Docker build check for the missing CLI image Dockerfile.
- [x] 2.2 Add a self-contained CLI image Dockerfile.
- [x] 2.3 Add Docker build context exclusions for local-only files and credentials.
- [x] 2.4 Verify the built image lists commands and starts `rarus-echo --help` without credentials.

## 3. GitHub Actions

- [x] 3.1 Add a Docker build workflow based on the issue example.
- [x] 3.2 Configure PR builds without package publication.
- [x] 3.3 Configure `dev`/`main` pushes and manual dispatch to publish multi-arch images to GHCR.
- [x] 3.4 Add OCI source, timestamp, and revision labels.

## 4. Documentation

- [x] 4.1 Document CLI Docker image usage in README.
- [x] 4.2 Update CHANGELOG for the CLI Docker image pipeline.

## 5. Validation and Delivery

- [x] 5.1 Run Docker image build verification.
- [x] 5.2 Run `make lint-openspec`.
- [x] 5.3 Run `git diff --check`.
- [x] 5.4 Run `make test-unit`.
- [x] 5.5 Run `make lint-all`.
- [x] 5.6 Push `feature/10-docker-image-build-pipeline` to `origin`.
- [x] 5.7 Open a pull request against `dev` with `Closes #10` and validation notes.
- [ ] 5.8 Check PR CI and agent review comments before reporting issue work complete.
