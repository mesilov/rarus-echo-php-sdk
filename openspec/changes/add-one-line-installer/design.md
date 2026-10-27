# Design

## Context

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/57. Packaging already produces Linux amd64/arm64, macOS arm64 and Windows amd64 archives plus SHA256SUMS. The latest stable Release 0.4.0 currently has no standalone assets. CLI identity is `RARUS Echo CLI <tag> (source <40-character source SHA>)`.

## Goals / Non-Goals

Install and update a standalone executable without elevated privileges, preserve connection config and user data, and retain distribution licenses. Do not build binaries, install system dependencies, publish releases, or run the separate connection setup command.

## Decisions

Use native POSIX sh and Windows PowerShell 5.1+ scripts. A Docker launcher would retain a runtime dependency; Composer bootstrap would require PHP. Native scripts match each platform and reuse the audited b24cli POSIX approach.

Resolve latest once to a public release tag; download both assets from that immutable tag. Explicit tags allow prereleases. Use HTTPS only and no authenticated API or CI fallback. Missing assets produce a clear failure.

Unix defaults: $HOME/.local/bin/rarus-echo and $HOME/.local/share/rarus-echo/package. Store a quoted, idempotent shell activation file alongside notices. Add one source line to zsh/bash startup files unless --no-modify-path. Print current-session activation; a pipe's child shell cannot change its parent. Windows defaults: LocalAppData/rarus-echo/bin and package; update user PATH unless -NoModifyPath and print current-session activation.

Before execution, verify one valid SHA256SUMS entry, reject traversal, duplicate/case-colliding names, links, special files and unexpected payloads, and require notices/runtime licenses. Validate candidate version output against the selected tag and a full source SHA; compare staged output before replacement. Stage on the destination filesystem. Preserve old binary/notices on failures before replacement. Configuration under .config or AppData is never read or written.

Offline tests replace only release transport/platform discovery and use executable fixtures. Test successful install/update, shell activation, unsupported platforms, malformed checksums/archives and preservation on failure. Native CI covers all targets; Windows tests execute a compiled fixture. Existing packaging runtime acceptance remains responsible for native runtime correctness.

## Risks / Trade-offs

- Latest stable has no archives yet → document the release prerequisite, fail clearly and test missing assets.
- No notarization/Authenticode → preserve existing build limitations; candidate launch failure must retain the old installation.
- The download checksum authenticates consistency with a release, not its publisher independently → use public HTTPS release URLs and existing GitHub trust.
- Multiple destination files cannot be committed atomically together → stage first and roll back replaced installer-owned entries on handled commit errors.

## Migration Plan

Merge PR into dev, later promote scripts to main through the release process. Publish a standalone release separately before announcing the commands as available. Rollback installer code through Git; users can select a previous release tag. Archive OpenSpec only after merge.
