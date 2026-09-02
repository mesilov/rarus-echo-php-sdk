## ADDED Requirements

### Requirement: Default Docker execution path
The transcribe skill SHALL make the published Docker CLI the default execution path and SHALL NOT instruct the agent to probe for host PHP, an installed `vendor/`, or a local binary before running a command.

#### Scenario: Docker is the unconditional default
- **WHEN** a user asks the skill to run any transcribe CLI workflow
- **THEN** the skill instructs the agent to run the published Docker CLI (`docker run ... :cli <command>`) directly
- **AND** it does not instruct the agent to probe for host PHP, an installed `vendor/`, or a local binary first

#### Scenario: Local execution is an explicit opt-in
- **WHEN** the user asks for local execution, or Docker is unavailable
- **THEN** the skill documents running `vendor/bin/rarus-echo` (or `php bin/rarus-echo` when there is no Composer bin proxy)
- **AND** it notes that local execution requires installed dependencies and a host PHP that satisfies the SDK requirement
