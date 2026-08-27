## ADDED Requirements

### Requirement: Self-contained CLI Docker image
The repository SHALL provide a Dockerfile that builds a self-contained RARUS Echo CLI image from repository source.

#### Scenario: CLI entrypoint is available
- **WHEN** the CLI Docker image is built
- **THEN** the image includes production Composer dependencies, SDK source code, and `bin/rarus-echo`
- **AND** the container entrypoint executes `rarus-echo`

#### Scenario: Default command lists CLI commands
- **WHEN** the CLI Docker image is run without command arguments
- **THEN** the command exits successfully without requiring RARUS Echo credentials
- **AND** the output includes the registered CLI commands

#### Scenario: Help works without credentials
- **WHEN** the CLI Docker image is run with `--help`
- **THEN** the command exits successfully without requiring RARUS Echo credentials

### Requirement: CLI Docker build pipeline
GitHub Actions SHALL build the CLI Docker image for supported release branches and pull requests.

#### Scenario: Pull request validation
- **WHEN** a pull request targets `dev` or `main` and changes CLI image inputs
- **THEN** GitHub Actions builds the CLI Docker image for `linux/amd64` and `linux/arm64`
- **AND** the workflow does not publish a package

#### Scenario: Release branch publication
- **WHEN** a push to `dev` or `main` changes CLI image inputs
- **THEN** GitHub Actions builds and publishes the CLI Docker image to GitHub Container Registry
- **AND** the image is tagged as `cli` and `cli-<git-sha>`

#### Scenario: Manual publication
- **WHEN** a maintainer starts the Docker build workflow manually
- **THEN** GitHub Actions builds and publishes the CLI Docker image to GitHub Container Registry

#### Scenario: OCI image metadata
- **WHEN** the workflow publishes the CLI Docker image
- **THEN** the image labels include the repository source URL, build timestamp, and git revision

### Requirement: Docker build context hygiene
The Docker build context SHALL exclude local-only files that are unrelated to the published CLI image.

#### Scenario: Local artifacts exist
- **WHEN** local dependencies, credentials, caches, downloads, or generated artifacts exist in the checkout
- **THEN** they are excluded from the Docker build context
