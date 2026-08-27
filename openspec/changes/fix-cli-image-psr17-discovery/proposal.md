## Why

The published CLI Docker image starts, but API-backed commands fail before making a request because the production Composer install has no PSR-17 implementation for HTTP discovery. This breaks the documented Docker happy path for submitting audio files.

## What Changes

- Include a PSR-17 implementation in production dependencies so SDK and CLI installs work with the default auto-discovered HTTP stack.
- Add a Docker image build-time smoke check for PSR-17 and PSR-18 discovery.
- Validate the Docker CLI happy path with local credentials and `downloads/test.webm`.

## Capabilities

### New Capabilities
- `cli-docker-runtime`: CLI Docker image runtime readiness and documented happy-path execution.

### Modified Capabilities

## Impact

- Affected files: `composer.json`, `composer.lock`, `docker/rarus-echo-cli/composer.lock`, `docker/rarus-echo-cli/Dockerfile`.
- Runtime dependency impact: `nyholm/psr7` becomes a production dependency instead of dev-only.
- Docker image impact: API-backed CLI commands fail at build time if HTTP discovery cannot instantiate required factories.
