## ADDED Requirements

### Requirement: Default HTTP Idle Timeout
The SDK SHALL build the auto-discovered HTTP client with an explicit idle timeout so large uploads are not aborted by the platform default (~60s).

#### Scenario: Default timeout for auto-discovered client
- **WHEN** an `ApiClientFactory` builds an `ApiClient` without a custom PSR-18 client
- **THEN** the HTTP client is created with a default idle timeout of 600 seconds
- **AND** a large multipart upload is not aborted with `Idle timeout reached` before the API responds

#### Scenario: Custom PSR-18 client is not overridden
- **WHEN** a caller supplies a client via `withHttpClient()`
- **THEN** the SDK uses that client as-is
- **AND** the idle timeout is the responsibility of the supplied client

### Requirement: Configurable HTTP Idle Timeout
The SDK SHALL allow the idle timeout of the auto-discovered HTTP client to be overridden programmatically and via the environment, and SHALL reject invalid values.

#### Scenario: Programmatic override
- **WHEN** a caller invokes `ApiClientFactory::withHttpTimeout(300)` before `build()`
- **THEN** the auto-discovered HTTP client uses a 300 second idle timeout

#### Scenario: Environment override
- **WHEN** `RARUS_ECHO_HTTP_TIMEOUT` is set to a positive integer and no explicit timeout is configured
- **THEN** the auto-discovered HTTP client uses that number of seconds as the idle timeout

#### Scenario: Explicit value takes precedence over environment
- **WHEN** both `withHttpTimeout()` is called and `RARUS_ECHO_HTTP_TIMEOUT` is set
- **THEN** the explicit `withHttpTimeout()` value is used

#### Scenario: Reject non-positive programmatic value
- **WHEN** a caller passes a non-positive value to `withHttpTimeout()`
- **THEN** an `InvalidArgumentException` is thrown

#### Scenario: Reject invalid environment value
- **WHEN** `RARUS_ECHO_HTTP_TIMEOUT` is set but is not a positive integer (empty, non-numeric, zero, negative, or fractional)
- **THEN** building the client throws an `InvalidArgumentException` naming the variable
