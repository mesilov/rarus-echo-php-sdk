# Verification

## Local evidence
- Python packaging contracts: 77 tests passed after native-review corrections, including 19 compressor contracts and two extraction-cache regressions and new compressed archive validation for all four target layouts.
- POSIX installer contracts: 25 tests passed (one native-archive case skipped without CI assets). Targeted complete/incomplete compression package cases also passed after stricter review fixes.
- `make test-unit`: 392 tests, 1088 assertions, 3 existing skips; no failures.
- `make lint-all`: OpenSpec, plugin reference, PHP CS Fixer, PHPStan and Rector passed. Existing Rector dependency deprecation messages remain.
- Docker address pools are exhausted on this host. Local unit/lint ran with a temporary Compose override disabling service networking; repository Compose configuration was unchanged.
- PHP bootstrap child-process checks passed in Docker for ordinary physical paths, launcher paths, invalid paths and clearing the launcher environment.
- Downloaded wrappe 1.0.4 crate SHA256 matches the b24cli pin; both exact build patches apply to that archive. Linux arm64 UPX SHA256 is from the official release asset digest.
- Workflow report shell passed `bash -n`; `sh -n install.sh` and `git diff --check` passed.
- Independent code review identified and corrected a missing workflow `fi` and incomplete installer provenance acceptance. Regression cases cover malformed provenance, a missing probe record and a missing original hash.

- Portable PowerShell 7 checks: 27 passed in the official Microsoft container. First Windows PowerShell 5.1 CI exposed a missing parenthesis around a mixed logical expression in the hash/size check; the existing tampered-hash regression reproduces it and passes after correction.
- GitHub Codex review of 58188c4 completed without findings. Both Docker acceptance architectures and standalone Linux amd64 passed.
- First macOS native original/final smoke passed, including physical launcher lookup, media conversion, signals and timeouts. Installed audio submission also passed, but a blanket TEMP-empty check rejected the intentional wrappe cache. Packaging now sets the explicit `rarus-echo-cache` name, records it in provenance, and acceptance permits only that declared cache while still rejecting PHAR/media leftovers. Independent review and 77 local contracts passed after this correction.
- First-run Linux amd64 executable: 67,088,314 to 21,397,506 bytes. First-run macOS executable: 68,682,016 to 22,309,568 bytes. These are measurements for 58188c4; final-head native acceptance remains required.

## Native verification
First-run Linux amd64 and arm64 passed completely. Windows original/final native smoke also passed; its installer fixture stopped on the already corrected PowerShell hash expression. Final-head native CI and complete macOS/Windows installation acceptance remain pending. No native platform is considered accepted based on fixture tests alone. Native acceptance must exercise original and compressed binaries, complete portable and installed media, signals, timeouts, copied Unicode paths and macOS extraction/cache behavior.
