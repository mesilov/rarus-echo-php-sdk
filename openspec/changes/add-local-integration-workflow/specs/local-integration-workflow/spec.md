## ADDED Requirements

### Requirement: Focused Local Integration Test Targets
The project SHALL provide Make targets for maintainers to run the full live integration suite and focused service-level suites.

#### Scenario: Maintainer runs focused suites
- **WHEN** a maintainer runs `make test-integration-core`, `make test-integration-queue`, `make test-integration-status`, or `make test-integration-transcription`
- **THEN** the target SHALL execute only the matching integration test path with coverage disabled

### Requirement: Local Integration Tests Guard Credentials
Live integration tests SHALL use shared credential guards that avoid printing secret values and skip when real local credentials are unavailable.

#### Scenario: Placeholder credentials remain configured
- **GIVEN** `RARUS_ECHO_API_KEY` or `RARUS_ECHO_USER_ID` is missing or set to documented placeholder values
- **WHEN** integration tests run
- **THEN** tests SHALL be skipped with an actionable message instead of making live API calls

### Requirement: README Documents Local Integration Setup
The README SHALL document local integration-test setup using `.env.local` and list the full and focused Make targets.

#### Scenario: Maintainer follows README setup
- **WHEN** a maintainer reads the local integration test section
- **THEN** it SHALL explain that tests use real API requests, require local credentials, and use ignored `.env.local` configuration
