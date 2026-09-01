## 1. Implementation

- [x] 1.1 Add OpenSpec proposal, design, spec delta, and task list for issue #26.
- [x] 1.2 Add failing agent-plugin validation covering required manifests, marketplaces, skill frontmatter, references, and CLI reference drift.
- [x] 1.3 Add the shared transcription skill, CLI reference, distribution reference, and host-specific plugin manifests.
- [x] 1.4 Add marketplace entries for Claude Code and Codex-compatible local/repo installation.
- [x] 1.5 Document installation and usage in `README.md`.
- [x] 1.6 Add a `CHANGELOG.md` `Unreleased` entry for the transcription skill.
- [x] 1.7 Run local validation: `make lint-openspec`, `make lint-agent-plugins`, `git diff --check`, `make test-unit`, and `make lint-all`.
- [x] 1.8 Push the issue branch, open a PR against `dev` with `Closes #26`, and check CI plus agent review threads.
