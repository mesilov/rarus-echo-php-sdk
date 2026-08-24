## Context

The SDK currently exposes service objects for transcription submission, status lookup, transcript retrieval, and queue inspection. Users can perform those operations from PHP code, but there is no Composer `bin` entrypoint for terminal workflows.

The CLI should follow the linked Command Line Interface Guidelines by using a real argument parser, returning meaningful exit codes, writing primary output to stdout, writing errors to stderr, and providing useful help. The project already depends on several Symfony components and has `symfony/console` in the lock file through development tooling, so promoting it to a runtime dependency is the smallest conventional PHP CLI option.

## Goals / Non-Goals

**Goals:**

- Add a Composer-installed `rarus-echo` executable.
- Provide commands for the common service operations: `submit`, `status`, `transcript`, and `queue`.
- Reuse existing SDK services without changing their public APIs.
- Load credentials from `RARUS_ECHO_API_KEY`, `RARUS_ECHO_USER_ID`, optional `RARUS_ECHO_BASE_URL`, and local `.env` files.
- Support readable text output and machine-readable JSON output.
- Keep commands unit-testable without live API credentials.

**Non-Goals:**

- Do not add interactive setup, stored credential files, or shell completion.
- Do not add long-running polling/watch behavior.
- Do not expose every period/list API variant in the first CLI version.
- Do not change the existing service, result, enum, or credential public contracts.
- Do not run integration tests unless live credentials are explicitly available.

## Decisions

- Use Symfony Console for the CLI application.
  - Rationale: it is the standard Symfony component for argument parsing, help, command testing, output sections, and exit code conventions. It also matches the CLI guideline to use a parser library.
  - Alternative considered: a custom `argv` parser. This would avoid one dependency, but it would duplicate parsing, help, and testing behavior.

- Allow Symfony Console 6.4, 7.x, and 8.0.x for the initial CLI dependency range.
  - Rationale: the repository currently uses PHPStan 1.x and Rector 1.x in CI. Without a tracked `composer.lock`, Composer can otherwise resolve Symfony Console 8.1.x, which PHPStan 1.x does not analyze correctly under the current dev-tooling set.
  - Alternative considered: upgrade PHPStan, PHPStan extensions, and Rector to their 2.x-compatible ranges. That is a broader dev-tooling migration than issue #11 needs.

- Keep CLI code under `Rarus\Echo\Cli`.
  - Rationale: the CLI is a public executable but not part of the core service API. A dedicated namespace keeps formatting, command parsing, and dependency construction away from `Services`.
  - Alternative considered: add methods directly to `ServiceFactory`. That would mix terminal concerns into SDK service construction.

- Introduce a small CLI client boundary.
  - Rationale: commands need to be unit tested without real API calls. `EchoClientInterface` and `EchoClientFactoryInterface` let command tests use in-memory fakes while the real adapter delegates to `ServiceFactory`.
  - Alternative considered: command tests could mock final SDK services indirectly. That would be brittle because many SDK classes are final/readonly and not designed as mocks.

- Load credentials lazily per command.
  - Rationale: `rarus-echo --help` and command help should not require credentials. Commands create the real SDK client only when they execute an API operation.
  - Alternative considered: build `ServiceFactory` during application startup. That would make help fail when environment variables are absent.

- Provide `--json` on each service command.
  - Rationale: text output is readable for humans, while JSON on stdout is stable for scripts. Errors continue to go to stderr.
  - Alternative considered: only text output. That would make automation parse labels and formatting.

- Make Composer `vendor` cache exact-keyed by Composer dependency metadata in GitHub Actions.
  - Rationale: this repository ignores `composer.lock`, so a cache key based only on `composer.lock` can restore stale dependencies after `composer.json` changes. Exact-keying by `composer.json` and future `composer.lock` prevents PHPStan/tests from running against an old cached `vendor` tree.
  - Alternative considered: keep broad Composer restore keys. This is faster on cold metadata changes, but it can reuse stale dependency trees and hide missing runtime packages.

## Risks / Trade-offs

- Dependency promotion changes package metadata. Mitigation: require `symfony/console` with Symfony 6.4, 7.x, and 8.0.x support while keeping the current CI tooling stable.
- CLI behavior may become too broad for a first release. Mitigation: keep the first command set to `submit`, `status`, `transcript`, and `queue`.
- API errors can leak stack traces if uncaught. Mitigation: commands catch operation failures, print concise messages to stderr, and return non-zero exit codes.
- Missing credentials can make basic usage confusing. Mitigation: help documents required environment variables and credential loading reports the missing variable name from existing `Credentials::fromEnvironment()`.
- Exact Composer cache keys reduce stale-cache risk at the cost of fewer partial cache hits. Mitigation: keep Docker layer caching and Composer's normal package cache.

## Migration Plan

1. Add the CLI OpenSpec artifacts.
2. Add Symfony Console to runtime requirements and configure Composer `bin`.
3. Add CLI client interfaces, real SDK adapter, application factory, and command classes.
4. Add command unit tests with fake clients.
5. Update README with installation, environment, and command examples.
6. Update GitHub Actions Composer cache keys to include Composer dependency metadata.
7. Run OpenSpec validation, unit tests, PHP linting, and diff whitespace checks.
8. Open or update a pull request against `dev`.

Rollback is removing the new `bin` entry, `src/Cli/` code, CLI tests, README section, and `symfony/console` runtime requirement.

## Open Questions

- None for the first CLI slice.
