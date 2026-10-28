# Verification

## Local evidence
- Python packaging contracts: 74 tests passed, including 18 compressor contracts and new compressed archive validation for all four target layouts.
- POSIX installer contracts: 25 tests passed (one native-archive case skipped without CI assets). Targeted complete/incomplete compression package cases also passed after stricter review fixes.
- `make test-unit`: 392 tests, 1088 assertions, 3 existing skips; no failures.
- `make lint-all`: OpenSpec, plugin reference, PHP CS Fixer, PHPStan and Rector passed. Existing Rector dependency deprecation messages remain.
- Docker address pools are exhausted on this host. Local unit/lint ran with a temporary Compose override disabling service networking; repository Compose configuration was unchanged.
- PHP bootstrap child-process checks passed in Docker for ordinary physical paths, launcher paths, invalid paths and clearing the launcher environment.
- Downloaded wrappe 1.0.4 crate SHA256 matches the b24cli pin; both exact build patches apply to that archive. Linux arm64 UPX SHA256 is from the official release asset digest.
- Workflow report shell passed `bash -n`; `sh -n install.sh` and `git diff --check` passed.
- Independent code review identified and corrected a missing workflow `fi` and incomplete installer provenance acceptance. Regression cases cover malformed provenance, a missing probe record and a missing original hash.

## Native verification
Native standalone CI and Windows installer execution are pending. No native platform is considered accepted based on fixture tests alone. Native acceptance must exercise original and compressed binaries, complete portable and installed media, signals, timeouts, copied Unicode paths and macOS extraction/cache behavior.
