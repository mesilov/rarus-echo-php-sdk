## ADDED Requirements

### Requirement: README Docker CLI Defaults
The README SHALL show Docker CLI quick-start commands without forcing a fresh image pull by default.

#### Scenario: Default examples do not force pull
- **WHEN** a user reads the Docker CLI quick-start examples
- **THEN** the example `docker run` commands do not include `--pull=always`

#### Scenario: Forced pull remains opt-in
- **WHEN** a user needs to refresh a locally cached CLI image tag
- **THEN** the README explains that `--pull=always` can be added explicitly
