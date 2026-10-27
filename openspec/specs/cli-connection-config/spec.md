# cli-connection-config Specification

## Purpose
Allow users to configure RARUS Echo CLI once and reuse a protected connection from any working directory.

## Requirements

### Requirement: Interactive setup
The CLI SHALL provide install to request API key, user ID and service URL, validate them and save a private connection file. Secret input SHALL be hidden without a visible fallback.

#### Scenario: First setup
- **WHEN** a user completes install without existing credentials
- **THEN** a valid config is created and its path is reported without credential values

#### Scenario: Noninteractive setup
- **WHEN** install runs without interaction
- **THEN** it fails clearly and writes no credentials

#### Scenario: Existing configuration
- **WHEN** install targets an existing file
- **THEN** replacement requires an explicit affirmative answer and cancellation preserves the file

### Requirement: Deterministic configuration paths
The CLI SHALL use absolute XDG_CONFIG_HOME or HOME/.config on Unix and APPDATA on Windows, under rarus-echo/config.json. Global --config PATH SHALL override the file for reading and install.

#### Scenario: Explicit relative path
- **WHEN** --config names a relative Unicode or spaced path
- **THEN** install and service commands resolve the same file relative to cwd

#### Scenario: No user directory
- **WHEN** no supported absolute user directory exists and no explicit path is given
- **THEN** configuration access fails with guidance to use --config

### Requirement: Single connection source
Explicit --config SHALL select only its file. Without it, existing environment/.env credentials SHALL precede the default user file. Partial or invalid selected credentials SHALL fail without mixing sources.

#### Scenario: Saved connection
- **WHEN** no legacy credentials are present and a default config exists
- **THEN** a service command uses that connection independently of cwd

#### Scenario: Explicit override
- **WHEN** ambient credentials and --config are both provided
- **THEN** only the explicit file supplies the connection

#### Scenario: Partial environment
- **WHEN** only one environment credential is supplied
- **THEN** the command fails without borrowing the other value from a config

### Requirement: Protected storage and errors
Files SHALL be private to the current user, written atomically after validation, and rejected if unsafe, unreadable or invalid. Errors SHALL omit credentials and file contents.

#### Scenario: Failed write or invalid input
- **WHEN** setup cannot validate values or establish private storage
- **THEN** it fails without replacing the previous valid config or leaving secret temporary files

#### Scenario: Invalid selected file
- **WHEN** a selected config is missing, malformed or exposed to other users
- **THEN** the command fails clearly without fallback or credential disclosure

### Requirement: Offline CLI compatibility
Help, version, list and install SHALL work without configured credentials. Existing environment and dotenv service invocations SHALL remain supported.

#### Scenario: Help without config
- **WHEN** the user requests help or version with no valid config
- **THEN** no connection is created and the CLI succeeds
