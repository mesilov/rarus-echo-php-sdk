## 1. OpenSpec

- [x] 1.1 Create proposal, spec delta, and task list for issue #43.
- [x] 1.2 Validate OpenSpec with `make lint-openspec`.

## 2. Implementation

- [x] 2.1 Build the auto-discovered HTTP client (Symfony PSR-18) with an explicit default idle timeout of 600s in `ApiClientFactory`.
- [x] 2.2 Add `ApiClientFactory::withHttpTimeout(int $seconds)` with validation for non-positive values.
- [x] 2.3 Support the `RARUS_ECHO_HTTP_TIMEOUT` environment override with precedence explicit > env > default, rejecting invalid values.
- [x] 2.4 Leave a caller-supplied `withHttpClient()` client untouched.

## 3. Tests

- [x] 3.1 Cover `withHttpTimeout()` fluent return and non-positive rejection.
- [x] 3.2 Cover build with an explicit timeout and with an environment timeout.
- [x] 3.3 Cover invalid environment values and explicit-over-environment precedence.

## 4. Documentation

- [x] 4.1 Document the default timeout, `RARUS_ECHO_HTTP_TIMEOUT`, and `withHttpTimeout()` in `README.md`.
- [x] 4.2 Add `CHANGELOG.md` Unreleased entries under Added and Fixed.

## 5. Verification

- [x] 5.1 Run `make lint-openspec`.
- [x] 5.2 Run `git diff --check`.
- [x] 5.3 Run `make test-unit`.
- [x] 5.4 Run `make lint-all`.
