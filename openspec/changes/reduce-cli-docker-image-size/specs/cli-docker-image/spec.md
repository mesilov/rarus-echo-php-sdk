## ADDED Requirements

### Requirement: Optimized CLI runtime base image
The CLI Docker image SHALL be built on an optimized official PHP runtime base that keeps the published image size small while preserving CLI runtime behavior.

#### Scenario: Alpine runtime base
- **WHEN** the CLI Docker image is built
- **THEN** the final runtime stage uses an official `php:8.4-cli-alpine` base image
- **AND** the image provides the `curl`, `fileinfo`, and `mbstring` PHP extensions
- **AND** the image includes a CA certificate bundle for TLS to the Echo API

#### Scenario: Reduced published image size
- **WHEN** the optimized CLI Docker image is compared to the previous Debian-based runtime image
- **THEN** the compressed pull size and displayed local size are meaningfully smaller
- **AND** the recorded before/after metrics are captured for the change

#### Scenario: CLI behavior preserved on the optimized base
- **WHEN** the optimized CLI Docker image is run without credentials
- **THEN** the `list` and `--help` commands exit successfully
- **AND** the PSR-17/PSR-18 discovery build smoke check passes during the image build
- **AND** the `rarus-echo` entrypoint and CLI PHP runtime limits are unchanged

#### Scenario: Multi-arch build preserved
- **WHEN** the optimized CLI Docker image is built by the release pipeline
- **THEN** it builds for `linux/amd64` and `linux/arm64`
