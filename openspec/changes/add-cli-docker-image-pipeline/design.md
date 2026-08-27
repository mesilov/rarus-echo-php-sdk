## Context

The repository already has `docker/php-cli/Dockerfile` for the local development environment. That image is intentionally workspace-mounted and starts an interactive PHP shell, so it is not a publishable CLI application image.

The CLI app from issue #11 is a Composer bin entrypoint at `bin/rarus-echo`. Issue #10 needs a build pipeline for that CLI app, not a replacement for the development container.

## Decisions

- Add a separate `docker/rarus-echo-cli/Dockerfile` for the publishable CLI image.
- Use the repository root as the Docker build context so the image can copy `composer.json`, `bin/`, and `src/` explicitly.
- Keep a Docker-image-scoped Composer lock file at `docker/rarus-echo-cli/composer.lock` so published `cli-<sha>` tags are reproducible without changing the library repository's root no-lock policy.
- Keep the runtime image self-contained: install production Composer dependencies during image build and expose `rarus-echo` through `ENTRYPOINT`.
- Do not copy `.env`, `.env.local`, local `vendor/`, caches, downloads, or local Composer artifacts into the Docker context; protect that with `.dockerignore`, while allowing the Docker-image-scoped lock file.
- Publish to `ghcr.io/mesilov/rarus-echo-php-sdk` with mutable `cli` and immutable `cli-<sha>` tags.
- Run the same workflow on pull requests with `push: false` so PRs validate that the image still builds without publishing packages.
- Reuse the linked b24phpsdk workflow shape: timestamp labels, GHCR login, QEMU, Buildx, multi-platform build, GitHub Actions layer cache, and OCI source/revision labels.

## Rollback

Remove `.github/workflows/docker-build.yml`, `docker/rarus-echo-cli/Dockerfile`, `.dockerignore`, and the README/changelog references. The existing development Docker environment remains unchanged.
