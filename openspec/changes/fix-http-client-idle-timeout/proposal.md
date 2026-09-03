## Why

Issue #43: submitting large files fails with an idle timeout raised by the auto-discovered Symfony HttpClient:

```
HTTP request failed: Idle timeout reached for "https://…/v1/async/transcription".
```

The SDK never configures a `timeout` for the auto-discovered PSR-18 client, so Symfony HttpClient falls back to PHP's `default_socket_timeout` (~60s). While a large multipart body is uploaded and the API has not started responding, the idle window is exceeded and the client aborts the request before a `file_id` is returned. This is a client-side timeout, not an API 5xx.

## What Changes

- Build the default (auto-discovered) HTTP client with an explicit idle timeout of `600` seconds instead of relying on the ~60s PHP default.
- Add `ApiClientFactory::withHttpTimeout(int $seconds)` to configure the idle timeout programmatically; reject non-positive values.
- Add a `RARUS_ECHO_HTTP_TIMEOUT` environment variable (positive integer seconds) that overrides the default; reject an invalid value.
- Precedence: explicit `withHttpTimeout()` value, then `RARUS_ECHO_HTTP_TIMEOUT`, then the built-in default.
- When a custom PSR-18 client is supplied via `withHttpClient()`, its timeout is the caller's responsibility and the SDK does not override it.

## Capabilities

### New Capabilities

- `http-client-idle-timeout`: the SDK configures a sensible, overridable idle timeout for the auto-discovered HTTP client so large uploads are not aborted prematurely.

### Modified Capabilities

- None.

## Impact

- Affected runtime code: `src/Core/ApiClientFactory.php`.
- Affected tests: `tests/Unit/Core/ApiClientFactoryTest.php`.
- Affected docs: `README.md` CLI section and `CHANGELOG.md`.
- Backward compatible: existing callers keep working; a custom `withHttpClient()` client is untouched. Behavior change is limited to a longer default idle timeout for the auto-discovered client.
