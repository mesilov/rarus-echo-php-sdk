## ADDED Requirements

### Requirement: Cross-agent transcription plugin
The repository SHALL provide a RARUS Echo transcription plugin that exposes one shared `transcribe` skill to Claude Code and Codex-compatible hosts.

#### Scenario: Claude Code plugin manifest exists
- **WHEN** a maintainer inspects `.agent-plugins/rarus-echo-transcription/.claude-plugin/plugin.json`
- **THEN** the manifest identifies the `rarus-echo-transcription` plugin and exposes the `transcribe` skill.

#### Scenario: Codex plugin manifest exists
- **WHEN** a maintainer inspects `.agent-plugins/rarus-echo-transcription/.codex-plugin/plugin.json`
- **THEN** the manifest identifies the `rarus-echo-transcription` plugin and exposes the `transcribe` skill.

#### Scenario: Shared skill entrypoint exists
- **WHEN** a host loads `.agent-plugins/rarus-echo-transcription/skills/transcribe/SKILL.md`
- **THEN** the skill contains valid frontmatter with the `transcribe` name and transcription workflow instructions.

### Requirement: Safe transcription workflows
The transcription skill SHALL document repeatable, secret-safe workflows for queue inspection, file submission, status lookup, transcript retrieval, and submit-with-wait.

#### Scenario: Queue inspection
- **WHEN** a user asks the skill to inspect the queue
- **THEN** the skill instructs the agent to run the CLI `queue --json` workflow without printing credential values.

#### Scenario: Submit local audio files
- **WHEN** a user asks the skill to submit one or more local audio files
- **THEN** the skill instructs the agent to mount audio files read-only for Docker execution and parse returned `file_ids`.

#### Scenario: Status lookup
- **WHEN** a user provides a `file_id` for status inspection
- **THEN** the skill instructs the agent to call `status <file-id> --json` and report non-terminal states accurately.

#### Scenario: Transcript retrieval
- **WHEN** a user provides a `file_id` for transcript retrieval
- **THEN** the skill instructs the agent to call `transcript <file-id> --json` and handle waiting or processing states as non-terminal results.

#### Scenario: Submit and wait
- **WHEN** a user asks for a complete transcript after submitting audio
- **THEN** the skill instructs the agent to prefer `submit --wait` and keep progress output separate from parseable stdout.

### Requirement: Distribution documentation
The repository SHALL document Claude Code and Codex installation paths for the transcription plugin.

#### Scenario: Claude Code install path is documented
- **WHEN** a maintainer reads the plugin distribution reference
- **THEN** it includes standalone skill and plugin marketplace installation paths for Claude Code.

#### Scenario: Codex install path is documented
- **WHEN** a maintainer reads the plugin distribution reference
- **THEN** it includes local or repository marketplace installation guidance for Codex-compatible hosts.

#### Scenario: Marketplace files exist
- **WHEN** a maintainer validates the repository
- **THEN** Claude Code and Codex marketplace JSON files exist and point to the repository-local plugin path.

### Requirement: CLI reference drift detection
The repository SHALL provide validation that fails when the checked-in skill CLI reference differs from current CLI help output.

#### Scenario: CLI reference is current
- **WHEN** `make lint-agent-plugins` runs in a checkout with dependencies installed
- **THEN** it compares the checked-in CLI reference with freshly generated help output for `queue`, `submit`, `status`, and `transcript`.

#### Scenario: CLI reference drifts
- **WHEN** a CLI option changes but `references/cli.md` is not refreshed
- **THEN** agent-plugin validation fails with instructions to run the CLI reference update script.

#### Scenario: Live credentials are not required
- **WHEN** `make lint-agent-plugins` validates the plugin
- **THEN** it MUST NOT require RARUS Echo API credentials or submit a live transcription request.
