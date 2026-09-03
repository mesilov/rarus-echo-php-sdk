## ADDED Requirements

### Requirement: README Documents the Complete CLI Option Reference
The README `## CLI` section SHALL contain a structured reference listing every CLI key: the global options available on all commands, and the arguments and options of each command (`queue`, `submit`, `status`, `transcript`) with their value requirements and defaults.

#### Scenario: User looks up a CLI key
- **WHEN** a user reads the README CLI reference
- **THEN** it SHALL list the global options (including `--json`)
- **AND** it SHALL list, per command, each argument and option with whether the option takes a value and its default where one exists

### Requirement: Maintainer Workflow Aligns CLI Documentation on CLI Changes
The maintainer workflow SHALL require that any change to CLI commands or options is reflected in both CLI documentation surfaces: the transcription skill CLI reference and the README `## CLI` section.

#### Scenario: Maintainer changes a CLI command or option
- **WHEN** the maintainer adds, removes, or changes a CLI command or option
- **THEN** the maintainer skill SHALL instruct regenerating the transcription skill CLI reference via `update-cli-reference.sh` (drift-checked by `make lint-agent-plugins`)
- **AND** the maintainer skill SHALL instruct updating the README `## CLI` section by hand, since it is not covered by drift validation
