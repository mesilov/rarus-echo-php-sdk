# Design

## Release source

Start from origin/dev at 1332d5be5d4da3098716cc6ff74a471554877f9a in the prepared #83 worktree. Use one release PR from the dev-based branch into main, following the existing RC-2 process, then synchronize reviewed release documentation and evidence back to dev through a closeout PR. PR #84 was initially opened against dev and retargeted to main without changing its head; all native checks build that exact head. Verify green native packaging, required checks and hosted agent review threads before merge. Tag the verified main merge commit as 0.5.0-rc-3 without replacing any existing tag.

## Changelog and documentation

RC-3 records changes since RC-2 plus a short cumulative overview. Earlier RC entries remain historical. Unreleased is empty; stable 0.5.0 is not dated. Explicit installation/Composer examples select RC-3. The preparation retained the historically verified RC-2 skill baseline. After all four public RC-3 targets pass and generated metadata matches the downloaded macOS binary, closeout advances the verified baseline to RC-3. The original RC-2 binary was explicitly checked to reject extract-audio, so it cannot verify that new command map. README retains permanent Latest URLs and its 0.4.0 availability notice.

## Verification and publication

Run make lint-openspec, make test-unit, make lint-all, packaging contracts, installer contracts and git diff --check. Reuse the existing Compose project network to avoid the host's exhausted new-network address pool. The existing tag workflow builds and accepts four native packages before uploading archives and SHA256SUMS; prerelease/latest=false keeps stable unchanged. Dispatch public release acceptance on main with the exact version and verify tag/source identity, public downloads, repeat installation and local smoke behavior on all targets.

## Closeout

Save the anonymous public macOS package, SHA256SUMS and matching main installer under downloads/0.5.0-rc-3. Verify checksum, archive metadata, embedded identity and the generated command map. Record URLs, full SHAs and checksums, archive only after the release PR merges, and synchronize release evidence into dev. Manual production connection/workflow acceptance and stable publication remain in #68; #72 awaits stable Latest archives.
