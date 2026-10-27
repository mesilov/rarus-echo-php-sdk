# Proposal

## Why

Issue #55 requires one-time setup for standalone CLI users, with persisted connection credentials and an explicit configuration-file override. Current CLI credentials depend on process environment or a project .env file.

## What Changes

- Add interactive install and a global --config PATH option.
- Store one validated connection in a private user JSON file under XDG/HOME on Unix or APPDATA on Windows.
- Resolve one complete connection source: explicit file, existing environment/.env credentials, then the default user file.
- Preserve credential-free help/version and existing SDK factory contracts; redact configuration validation errors.
- Update command documentation, generated CLI reference and native acceptance.

## Capabilities

### New Capabilities

- `cli-connection-config`: Interactive connection setup, private storage, deterministic source selection and configurable file paths.

### Modified Capabilities

None. The existing standalone-cli change concerns executable packaging; this change adds connection setup behavior.

## Impact

Console infrastructure, client creation and command wiring change. Core SDK Credentials and service APIs retain their signatures and environment behavior. Tests cover source precedence, private storage and native platform acceptance. No new dependency is required.
