## ADDED Requirements

### Requirement: Runtime distribution notices
Installers SHALL accept archives without b24cli or YiiPress build-helper license files while requiring application notices and runtime/media licenses. They SHALL also install historical archives containing those two files and preserve the frozen RC-1 no-media contract.

#### Scenario: Install reduced archive
- **WHEN** a valid new archive omits BITRIX24-CLI-LICENSE.txt and YIIPRESS-LICENSE.txt
- **THEN** both installers install the complete runtime package and required licenses

#### Scenario: Update from historical notices
- **WHEN** an installation containing the two historical license files is updated from a valid reduced archive
- **THEN** the active notices directory matches the new package without stale build-helper licenses and user configuration is preserved

#### Scenario: Required runtime license missing
- **WHEN** either archive shape lacks runtime licenses or a required media license
- **THEN** installation fails before replacing the current installation
