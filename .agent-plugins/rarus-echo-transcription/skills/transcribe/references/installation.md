# Install the RARUS Echo CLI

## Missing or broken executable

Offer to install the standalone CLI for the user's OS and architecture. Do not
install without authorization; honor installation authorization already given.
For an existing installation, first check whether its known executable path works
and only PATH activation is missing. Do not reinstall just because a newly opened
agent shell has an older PATH. Do not change credentials while repairing PATH.

Supported packages: Linux amd64/arm64, macOS arm64, Windows amd64. Other targets,
including Intel macOS and Windows ARM64, have no standalone package in this baseline;
explain that limitation instead of silently switching to Docker or PHP.

## Release selection

Target contract: **0.5.0**. Verified published baseline on **2026-10-28**:
[0.5.0-rc-2](https://github.com/mesilov/rarus-echo-php-sdk/releases/tag/0.5.0-rc-2),
with all six project commands and bundled media tools. This is a prerelease;
stable **0.4.0** has no standalone archives. Do not claim stable 0.5.0 is published.

Before offering an installation or upgrade, check
[releases](https://github.com/mesilov/rarus-echo-php-sdk/releases) for an appropriate
tag and platform asset. Prefer stable 0.5.0 once its assets are published and
verified. If only the RC is suitable, say so and let the user choose the prerelease;
do not substitute it for an authorized stable installation. Do not use an
unversioned installer assuming Latest already contains standalone packages.

## Installer examples for the verified RC

Linux amd64/arm64 or macOS Apple Silicon:

```bash
curl -fsSL --proto '=https' --proto-redir '=https' https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.sh | sh -s -- --version 0.5.0-rc-2
```

Windows amd64, PowerShell 5.1 or newer:

```powershell
& ([scriptblock]::Create((Invoke-WebRequest -UseBasicParsing https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.ps1).Content)) -Version 0.5.0-rc-2
```

When selecting another verified release, replace the version explicitly. The
installers verify SHA-256 and install into a user directory without PHP, Composer,
Docker, or administrator privileges. POSIX requires curl, tar and SHA-256 tools.
Keep the full installed package, including media tools and licenses.

Default executable: `$HOME/.local/bin/rarus-echo` on POSIX or
`%LOCALAPPDATA%\rarus-echo\bin\rarus-echo.exe` on Windows. Follow the installer's
PATH activation instructions; an existing shell may need restarting. Verify the
selected executable with `--version` and `help submit --format=json` afterward.

## Connection setup

The executable installer does not configure Echo credentials. Let the user enter
them privately in an interactive terminal:

```bash
rarus-echo install
```

This prompts for API key (hidden input), User ID and service URL. Do not gather
secrets through chat or pass them as literal command arguments. Do not overwrite
an existing connection without the user's intent. `install --no-interaction`
returns 2 without writing; it is not an unattended configuration method.

Global `--config PATH` selects a connection file, including for interactive
`install`. Default location: `$XDG_CONFIG_HOME/rarus-echo/config.json` when XDG is
absolute, otherwise `$HOME/.config/rarus-echo/config.json`; Windows uses
`%APPDATA%\rarus-echo\config.json`.

Selection precedence:

1. Explicit `--config PATH`: use only that file; no env/dotenv credential fallback.
2. Otherwise load working-directory dotenv as the CLI does. If API key or User ID
   is set, validate the entire environment credential set without merging a file.
3. Otherwise read the default connection file. A base URL alone does not override
   its saved URL.

Existing variables: `RARUS_ECHO_API_KEY`, `RARUS_ECHO_USER_ID`, optional
`RARUS_ECHO_BASE_URL`. Never display their values or config/env-file contents.
For authorized connection diagnostics, run:

```bash
rarus-echo doctor --json
# Or select an existing connection:
rarus-echo --config "/path/connection.json" doctor --json
```

Doctor contacts Echo and returns 0 for ok/warning, 1 for failed checks and 2 for
invalid usage. Report failed checks and propose the CLI's remedy; do not bypass
it with SDK or HTTP calls.
