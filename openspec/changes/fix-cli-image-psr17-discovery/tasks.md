## 1. Dependency And Image Runtime

- [x] 1.1 Move `nyholm/psr7` from dev-only dependencies to production dependencies and update root and Docker CLI lock files.
- [x] 1.2 Add a Dockerfile smoke check for PSR-17 and PSR-18 discovery after production autoload generation.
- [x] 1.3 Add CLI image PHP runtime limits with `memory_limit=4G` and upload limits for local audio files up to 500 MB.

## 2. Verification

- [x] 2.1 Verify the smoke check fails on the current production dependency set before the dependency fix.
- [x] 2.2 Build the CLI Docker image locally after the dependency fix.
- [x] 2.3 Verify the fixed image reports the expected PHP runtime memory limit.
- [x] 2.4 Run the Docker CLI image with local credentials and `downloads/test.webm`, confirming `submit --json` returns file identifiers.
- [x] 2.5 Run repository validation: `make lint-openspec`, `git diff --check`, `make test-unit`, and `make lint-all`.
