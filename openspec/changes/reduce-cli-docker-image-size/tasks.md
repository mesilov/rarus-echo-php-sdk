## 1. Runtime Base Optimization

- [x] 1.1 Switch the `vendor` and `cli` stages in `docker/rarus-echo-cli/Dockerfile` to `php:8.4-cli-alpine`.
- [x] 1.2 Replace Debian `apt-get` package installs with Alpine `apk` (`git`, `unzip` in the build stage; `ca-certificates` in the runtime stage).
- [x] 1.3 Keep the `curl`, `fileinfo`, and `mbstring` extension guards, the PSR-17/PSR-18 discovery build smoke check, the CLI `php.ini` runtime limits, and the `rarus-echo` entrypoint.

## 2. Documentation

- [x] 2.1 Update the README Docker CLI section for the new Alpine runtime base and size expectations.
- [x] 2.2 Add a `CHANGELOG.md` `Unreleased` entry describing the CLI image size reduction.

## 3. Verification

- [x] 3.1 Build the current baseline image locally and record `docker images` size, `docker image inspect` size, and `/app`, `/usr`, `/usr/local` directory sizes. (bookworm: `docker images` 769MB, inspect 181166120 B, `/app` 18M, `/usr` 531M)
- [x] 3.2 Build the Alpine CLI image locally and record the same metrics; confirm a meaningful compressed/local size reduction (target at least 30%). (alpine: `docker images` 179MB, inspect 45865394 B, `/app` 17.2M, `/usr` 100.2M, `/usr/local` 56.7M; `docker save | gzip` 45.6MB vs 179.5MB baseline ≈ 74.6% smaller; `docker images` ≈ 76.7% smaller)
- [x] 3.3 Verify the PSR-17/PSR-18 discovery build smoke check passes in the Alpine image. (vendor stage `RUN php -r` discovery step passed on both platforms)
- [x] 3.4 Run `docker run --rm <image> list` and `--help` and confirm they exit `0` without credentials, and that `php -m` reports `curl`, `fileinfo`, and `mbstring`. (both exit 0; extensions present)
- [x] 3.5 Confirm the Alpine image builds for `linux/amd64` and `linux/arm64` via `docker buildx build --platform linux/amd64,linux/arm64`. (build exit 0)
- [~] 3.6 With local Echo credentials, verify `queue --json` reaches the Echo API and exits `0`, and `submit` with a small mounted audio fixture returns submitted file identifiers. (`queue --json` exit 0 with valid JSON against production; `fileinfo` MIME detection validated on the Alpine image for the multipart path. A full production `submit` was not run because no small audio fixture exists locally and the available fixtures are 92–125 MB uploads that create real production transcription jobs — pending maintainer decision.)
- [x] 3.7 Run repository validation: `make lint-openspec`, `git diff --check`, `make test-unit`, and `make lint-all`. (all green: unit `OK (209 tests, 553 assertions)`, lint-openspec 13 passed, CS Fixer clean, PHPStan `[OK] No errors`, Rector `[OK]`)

## 4. Pull Request

- [ ] 4.1 Push the `feature/25-reduce-cli-docker-image-size` branch and open a pull request into `dev` with `Closes #25`.
- [ ] 4.2 Verify PR CI (Docker build, lint, tests) and process agent review threads.
