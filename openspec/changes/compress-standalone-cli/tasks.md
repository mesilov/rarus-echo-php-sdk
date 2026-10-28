# Tasks

## 1. Verified packers
- [x] 1.1 Port pinned b24cli compressors and notices; add verified Linux arm64 and tests for invalid digests, cache integrity, failures, ELF boundaries and reports.
- [x] 1.2 Preserve original launcher location through macOS runner/PHAR bootstrap; verify cold/warm Unicode media discovery and document cache requirements.

## 2. Distribution integration
- [x] 2.1 Integrate original/final packaging and native smoke; validate unchanged PHAR bytes and runtime caches, reduction reports and signatures.
- [ ] 2.2 Extend archive/installer contracts compatibly with historical releases; test compression provenance, missing notices, tampered bytes and complete installation.
- [x] 2.3 Update workflow inputs/cache keys and Rust prerequisites; update README/build docs, attribution and CHANGELOG and check workflow syntax.

## 3. Integration verification and review
- [x] 3.1 Run packaging contracts, make lint-openspec, git diff --check, make test-unit and make lint-all; record actual results.
- [ ] 3.2 Open PR into dev with Closes #74, pass all four native CI targets and handle agent review comments before completion.

## Workflow follow-up
- Archive only after the linked PR has merged; no merge/release is authorized by this issue.
