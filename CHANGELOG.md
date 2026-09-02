# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Parallel per-issue worktree tooling (`make worktree-new`, `worktree-remove`, `worktree-list` backed by a maintainer-skill script `.agents/skills/rarus-echo-maintainer/scripts/worktree.sh`): creates a git-ignored `.worktree/<issue>-<slug>` checkout branched off `origin/<base>`, provisioned with a symlinked `.env.local` and an independent clone-copied `vendor/`, and removes it while keeping the branch.
- Cross-agent `rarus-echo-transcription` plugin with a shared transcription skill, marketplace entries, and CLI reference drift validation for agent sessions.
- CLI `submit --wait` can now submit audio and poll until terminal transcript results, including JSON, raw transcript, and output-file modes while keeping progress on stderr.
- Long-running `submit --wait` commands now handle `SIGINT` and `SIGTERM` gracefully by writing a shutdown message to stderr and exiting with a signal-aware non-zero status.
- GitHub issue templates for bug reports and release rollout requests.

### Changed
- README now displays CI status badges for the Lint and Tests GitHub Actions workflows.
- README Docker CLI examples no longer use `--pull=always` by default; the pull flag remains documented as an opt-in image refresh.
- Maintainer workflow now creates issue worktrees with `make worktree-new`, branching directly off `origin/<base>` instead of first updating local `dev`.
- Maintainer workflow now documents release changelog rollover and README installation example updates.
- Distributed Composer package is now lean: `.gitattributes` `export-ignore` excludes development, CI, tooling, docs-process, and agent/maintainer files, leaving consumers only the runtime SDK.

## [0.3.0] - 2026-08-27

### Added
- CLI app `vendor/bin/rarus-echo` for queue, status, transcript, and submit service operations.
- Docker image build pipeline for the CLI app with GHCR publication.
- Local integration test targets for core, queue, status, and transcription services.
- OpenSpec-backed maintainer workflow and repo-local agent skill documentation.

### Changed
- Docker CLI image usage is now the first quick-start path in the README.
- README now keeps release examples inline instead of referencing a separate examples directory.
- Contributor documentation now matches PHP 8.4/8.5 requirements and current SDK namespaces.

### Fixed
- CLI Docker image now includes production PSR-17 discovery dependencies and a 4G PHP memory limit for large audio submissions.

## [0.2.0] - 2026-08-24

### Added
- PHP 8.4 support.
- Initial SDK implementation for asynchronous Echo transcription operations.
- PSR-compatible HTTP client, request factory, stream factory, and logger integration.
- Service layer for transcription, status, and queue operations.
- Unit and integration test coverage for SDK services.

## [0.1.0] - 2026-08-22

### Added
- Initial package scaffold.
