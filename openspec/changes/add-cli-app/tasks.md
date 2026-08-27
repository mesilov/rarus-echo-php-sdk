## 1. Package and OpenSpec Setup

- [x] 1.1 Validate the `add-cli-app` OpenSpec artifacts before PHP implementation.
- [x] 1.2 Add Symfony Console to runtime requirements and declare `bin/rarus-echo` in Composer metadata.
- [x] 1.3 Add the executable `bin/rarus-echo` wrapper and ensure it boots without credentials for help output.

## 2. CLI Infrastructure

- [x] 2.1 Add failing unit tests for CLI application help and command registration.
- [x] 2.2 Implement `Rarus\Echo\Infrastructure\Console\ApplicationFactory` and register service commands.
- [x] 2.3 Add failing unit tests for environment-backed client creation.
- [x] 2.4 Implement CLI client interfaces, SDK adapter, and environment client factory.
- [x] 2.5 Add shared command helpers for JSON output, UUID parsing, and concise error handling.

## 3. Service Commands

- [x] 3.1 Add failing unit tests for `queue` text, JSON, and service failure behavior.
- [x] 3.2 Implement the `queue` command.
- [x] 3.3 Add failing unit tests for `status` text, JSON, invalid UUID, and service failure behavior.
- [x] 3.4 Implement the `status` command.
- [x] 3.5 Add failing unit tests for `transcript` text, JSON, invalid UUID, and service failure behavior.
- [x] 3.6 Implement the `transcript` command.
- [x] 3.7 Add failing unit tests for `submit` default options, explicit options, JSON, invalid enum values, and service failure behavior.
- [x] 3.8 Implement the `submit` command.

## 4. Documentation

- [x] 4.1 Update README with CLI installation, credential environment variables, and command examples.
- [x] 4.2 Update CHANGELOG with the CLI app addition without staging unrelated pre-existing local edits.

## 5. Validation and Delivery

- [x] 5.1 Run focused CLI unit tests.
- [x] 5.2 Run `make lint-openspec`.
- [x] 5.3 Run `git diff --check`.
- [x] 5.4 Run `make test-unit`.
- [x] 5.5 Run `make lint-all`.
- [x] 5.6 Push `feature/11-cli-app` to `origin`.
- [x] 5.7 Open a pull request against `dev` with `Closes #11` and local validation notes.
- [x] 5.8 Diagnose the failed PR `Code Quality` check and update Composer cache keys.
- [x] 5.9 Reproduce the no-lock PHPStan failure and constrain Symfony Console to the CI-compatible initial range.
- [x] 5.10 Address Codex review comments for Composer bin autoloading and raw CLI output.
- [x] 5.11 Push the CI and review fixes to `feature/11-cli-app`.
- [x] 5.12 Check PR CI and agent review comments before reporting issue work complete.
- [x] 5.13 Move CLI implementation to `Infrastructure\Console` and root Echo client contracts under `Contracts`.
