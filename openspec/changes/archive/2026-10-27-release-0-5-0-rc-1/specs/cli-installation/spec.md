## MODIFIED Requirements

### Requirement: Idempotent PATH activation
The installers SHALL configure user PATH idempotently, support opting out, and print activation instructions for the current shell. Unix startup changes SHALL respect ZDOTDIR and bash's existing login profile. Windows SHALL update only user PATH and preserve raw variable references, value type and original existence during update or rollback.

#### Scenario: PATH modification disabled
- **WHEN** --no-modify-path or -NoModifyPath is passed
- **THEN** shell profiles and persistent PATH remain unchanged and manual activation instructions are printed

#### Scenario: Expandable Windows PATH
- **WHEN** existing user PATH contains expandable variable references
- **THEN** installation retains those references and their registry value type, and rollback restores the original raw value, type and existence

## ADDED Requirements

### Requirement: Unicode Windows bootstrap
The Windows installer SHALL initialize native architecture detection and environment notification in PowerShell 5.1 or newer when temporary and working directories contain Unicode characters, without changing temporary-directory environment variables or weakening directory permissions.

#### Scenario: Unicode temporary directory
- **WHEN** a fresh PowerShell process starts the installer with Unicode TEMP and working directories
- **THEN** native architecture detection and bounded environment notification succeed, repeated initialization succeeds, and no compiler files remain
