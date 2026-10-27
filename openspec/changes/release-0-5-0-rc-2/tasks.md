# Tasks

## 1. Prepare the release source

- [x] 1.1 Merge verified PR #67 into dev; verify expected head 5400887, green CI, no unresolved agent threads and merge commit e46d969.
- [x] 1.2 Archive merged doctor then media, add the selected-RC documentation delta and update CHANGELOG/explicit README examples; verify canonical diagnostic/media specs, preserved rc-1 history and exact rc-2 version.
- [x] 1.3 Preserve toolkit-free audio-only WebM submission; verify the actual committed fixture, video/manual-selection rejection and bounded malformed-container cases before rerunning all local gates.
- [x] 1.4 Run make lint-openspec, make test-unit, make lint-all, Python build contracts and git diff --check; record the successful commands.

## 2. Promote to main

- [x] 2.1 Open the dev-based #69 release PR into main; verify all applicable CI checks and handle inline/top-level agent feedback before merging the checked source.
- [x] 2.2 Verify main contains the release source and create exact 0.5.0-rc-2 tag; verify the tag points to that full main SHA without replacing earlier tags.

## 3. Publish and accept

- [ ] 3.1 Wait for the tag workflow's four native builds, aggregate archive validation and publication; verify four archives, SHA256SUMS, prerelease metadata and unchanged Latest stable.
- [ ] 3.2 Dispatch public acceptance on main for the exact rc-2 tag/source; verify all four platform jobs pass with public downloads and installers.
- [ ] 3.3 Save the public macOS arm64 archive, SHA256SUMS and main installer in a separate local downloads/0.5.0-rc-2 directory; verify archive identity/hash and provide the manual handoff path without replacing the user's own acceptance.

## Workflow follow-up

- After the release PR merges and public acceptance succeeds, archive this release change and record immutable release/source/run evidence.
- Synchronize release documentation and closeout back to dev through a green reviewed PR.
- Close #69 after the release checks succeed; keep stable/manual acceptance tracked in #68.
