## Why

The 0.3.0 milestone is ready to ship, but the public documentation still has stale release notes and references to example files that do not exist in the repository. Release documentation should show the Docker CLI happy path and PHP examples directly in the README.

## What Changes

- Update README examples inline instead of creating an `examples/` directory.
- Align contributor documentation with PHP 8.4/8.5 requirements and current SDK namespaces.
- Mark the 0.3.0 changelog section as released and remove stale historical release-note text.
- Verify documentation snippets and standard project checks.

## Capabilities

### New Capabilities
- `release-documentation`: Release documentation and example guidance for shipping version 0.3.0.

### Modified Capabilities

## Impact

- Affected files: `README.md`, `CONTRIBUTING.md`, `CHANGELOG.md`.
- Runtime SDK impact: none.
- Release impact: issue #12 can close after documentation checks and PR validation pass.
