## MODIFIED Requirements

### Requirement: Documented preparation controls

Command help, the hand-written docs/cli.md guide linked from README, and the maintained transcription-skill CLI reference SHALL document automatic extraction, the three media options, stream-index semantics, resource limits, toolkit location and bypass behavior. Documentation MUST distinguish native distribution support from optional Composer media-tool configuration.

#### Scenario: Discover video preparation behavior

- **WHEN** a user follows the README link to the CLI guide, reads submit --help or the maintained command reference
- **THEN** they can understand the automatic path and use --audio-stream, --no-extract-audio and --media-timeout without changing the default one-command workflow
