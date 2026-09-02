## Context

The CLI Docker image publishes `bin/rarus-echo` as a self-contained container. The current Dockerfile uses `php:8.4-cli-bookworm` for both the `vendor` build stage and the final `cli` runtime stage.

Measured baseline (issue #25, reproduced locally on `linux/arm64`):

```text
docker images displayed size: 769MB
docker image inspect size:    181166120 bytes (~173 MiB)
/app:  18MB
/usr:  531MB
```

The application/vendor footprint (`/app`) is small; the size is dominated by the Debian-based PHP runtime layers. Any meaningful reduction must target the runtime base, not `/app`.

## Decision

Adopt issue #25 Option 1: switch the final runtime image (and the matching build stage) to the official `php:8.4-cli-alpine` base.

Rationale:

- It is the lowest-complexity way to attack the root cause (a large base runtime) while staying on an official PHP image.
- It keeps the Dockerfile easy to reason about and does not require assembling a custom runtime.
- The `php:8.4-cli-alpine` base is roughly `39MB` (`docker image inspect`) / `151MB` (`docker images`) versus the Debian base, so the expected reduction is large.

Implementation details that preserve current behavior:

- Keep the two-stage `vendor` → `cli` structure. Both stages move to `php:8.4-cli-alpine` so vendor packages resolve and the discovery smoke check runs on the same runtime that ships.
- Replace `apt-get install git unzip` with `apk add --no-cache git unzip` in the build stage, and `apt-get install ca-certificates` with `apk add --no-cache ca-certificates` in the runtime stage so TLS trust is present for the Echo API.
- Keep the `mlocati/php-extension-installer` guard for `curl`, `fileinfo`, and `mbstring`. The installer works on Alpine and pulls the correct musl runtime libraries (for example `libcurl` and `oniguruma`) while cleaning up build-only packages, so the image stays small and the extensions match the current set.
- Keep the PSR-17/PSR-18 discovery build smoke check after optimized autoload generation, so a musl-specific dependency regression fails the build before publication.
- Keep `docker/rarus-echo-cli/conf.d/php.ini` (4G memory limit, 500M upload/post limits, UTC timezone) so local audio smoke tests behave as before.
- Keep the `rarus-echo` entrypoint symlink and multi-arch build for `linux/amd64` and `linux/arm64`.

## Risks

- Alpine uses musl instead of glibc, so subtle runtime differences are possible. Mitigation: build-time PSR discovery smoke check plus live-API `queue`/`submit` smoke tests before treating the change as done.
- `date.timezone` is `UTC`, which PHP resolves without the system `tzdata` package, so `tzdata` is intentionally not added. If a future requirement needs non-UTC zone data, add `tzdata` explicitly.

## Alternatives Considered

- Issue #25 Option 2: build a custom slim Debian runtime stage from `debian:bookworm-slim` and copy only required PHP binaries, extensions, and shared libraries. Rejected as the first attempt because it is higher maintenance, easy to break (missing shared libraries, CA bundle, or extension dependencies), and makes security updates harder to reason about. It remains the documented fallback if Alpine introduces compatibility problems that cannot be resolved.
- Optimize only `/app`. Rejected because `/app` is `18MB` of a `769MB` image and cannot reach the size target.
