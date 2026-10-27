# Design

## Context

Issue #61 builds on install/config selection from #55. The console has injectable clients and protected JSON storage. See proposal.md for motivation.

## Goals / Non-Goals

Expose three independent readiness checks with bounded I/O and deterministic tests. No configuration writes, dependency installation, extraction or transcription submission.

## Decisions

- Keep doctor presentation separate from configuration, Echo and ffmpeg checks. Use immutable CheckResult values (status, code, message, details) and a configuration diagnosis carrying private credentials for the server probe.
- Extend the internal resolver with source metadata while retaining resolve() and existing precedence. Resolve the connection before inspecting its selected/default file so dotenv changes to XDG/HOME/APPDATA are included. Inspect the file separately even when environment credentials are active. A typed dotenv loading error preserves the actual cause separately from file_status. An invalid unused file is a warning; invalid active settings are an error. Configuration file paths are shown, credentials and raw exception/response/process output are not.
- Use the existing authenticated queue endpoint through a dedicated Symfony HTTP client with idle and total timeouts of ten seconds and redirects disabled. Classify status before reading JSON so HTML error pages keep their authentication/server category. Reusing the SDK response decoder would hide such statuses. This keeps upload timeouts unchanged. Report authentication, transport, HTTP server and unexpected response failures separately.
- Add Symfony Process for executable discovery (including Windows PATH/PATHEXT), argument-array invocation and a five-second ffmpeg version timeout. Native proc_open would duplicate portable lifecycle and timeout handling. Only a validated ffmpeg version token is returned.
- Report checks in stable config/connection/ffmpeg order. Statuses: ok, warning, error, skipped. Overall error when any error/skipped, warning when only warnings, otherwise ok. Exit 1 for overall error, 0 otherwise. CLI syntax errors retain Symfony code 1. JSON has schema_version=1, status and named checks with status/code/message/details objects.

## Risks / Trade-offs

- Missing optional saved config with valid environment credentials → warning, while the authenticated server check still runs.
- Untrusted API or process errors may echo credentials → fixed diagnostic messages, never raw exception details or full process output.
- ffmpeg in Docker is checked inside the running container → document that installation must target that environment.
- New Symfony dependency → declare production requirement and update CLI runtime lock if needed.

## Validation plan

Use fixture config files, fake/protocol HTTP responses and controlled executable fixtures. Run focused tests first, then make test-unit, make lint-all, git diff --check. Open a PR to dev; retain OpenSpec artifacts until merge.
