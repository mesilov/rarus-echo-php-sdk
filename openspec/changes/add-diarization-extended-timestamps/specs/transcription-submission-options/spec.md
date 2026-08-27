## ADDED Requirements

### Requirement: Extended Timestamps Option
The SDK SHALL expose an optional transcription submission setting for the OpenAPI `timestamps-extended` header.

#### Scenario: Default headers disable extended timestamps
- **WHEN** SDK code builds default transcription options
- **THEN** the generated submission headers include `timestamps-extended` with value `0`

#### Scenario: Builder enables extended timestamps
- **WHEN** SDK code builds transcription options with extended timestamps enabled
- **THEN** the generated submission headers include `timestamps-extended` with value `1`

### Requirement: CLI Extended Timestamps Flag
The CLI SHALL allow `submit` users to request diarization with extended timestamps without bypassing the SDK option model.

#### Scenario: Submit parses extended timestamps flag
- **WHEN** a user runs `rarus-echo submit audio.mp3 --task-type=diarization --language=ru --speakers-correction --timestamps-extended --json`
- **THEN** the submitted transcription options use `task-type` value `diarization`, `language` value `ru`, `speakers-correction` value `1`, and `timestamps-extended` value `1`

#### Scenario: Submit help documents extended timestamps flag
- **WHEN** a user runs `rarus-echo submit --help`
- **THEN** the help output lists the `--timestamps-extended` option
