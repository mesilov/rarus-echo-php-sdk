## Context

`ApiClientFactory::build()` relies on `php-http/discovery` to find PSR-18 and PSR-17 implementations when the caller does not inject them explicitly. The CLI Docker image installs Composer dependencies with `--no-dev`, while the only concrete PSR-17 implementation currently available in the repository lock is `nyholm/psr7` from `require-dev`.

As a result, the image can list commands, but `queue`, `status`, `transcript`, and `submit` fail while creating the SDK client:

```text
Error: Unexpected exception when instantiating class.
```

The wrapped discovery error is missing PSR-17 request/stream/response factories.

## Decision

Move `nyholm/psr7` to production dependencies. This preserves the existing auto-discovery design and avoids adding container-specific wiring or custom factory code to CLI commands.

Add a Dockerfile smoke check immediately after optimized production autoload generation. The check loads `/app/vendor/autoload.php` and verifies:

- `Psr17FactoryDiscovery::findRequestFactory()`
- `Psr17FactoryDiscovery::findResponseFactory()`
- `Psr17FactoryDiscovery::findStreamFactory()`
- `Psr18ClientDiscovery::find()`

This makes the Docker build fail before publication if production dependencies cannot support the CLI HTTP stack.

The CLI image also needs practical PHP limits for local audio smoke tests. Large audio uploads can otherwise fail before the request completes because the base PHP CLI image defaults to `memory_limit=128M`; the CLI image uses a `4G` memory limit and `500M` upload/post limits.

## Alternatives Considered

- Instantiate Symfony HTTP client and factories manually in the CLI factory. Rejected because the SDK already standardizes on PSR auto-discovery and should work outside Docker as well.
- Keep `nyholm/psr7` dev-only and install dev dependencies in the image. Rejected because the CLI image should remain a production install.
