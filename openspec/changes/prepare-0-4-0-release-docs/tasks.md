## 1. Release documentation

- [x] 1.1 Roll `[Unreleased]` changelog entries into `## [0.4.0] - 2026-09-03` and keep an empty `## [Unreleased]` heading above it.
- [x] 1.2 Pin the README installation example to `composer require mesilov/rarus-echo-php-sdk:^0.4`.

## 2. Verification

- [x] 2.1 Run `make lint-openspec`.
- [x] 2.2 Run `git diff --check`.
- [x] 2.3 Run `make test-unit`.
- [x] 2.4 Run `make lint-all`.
