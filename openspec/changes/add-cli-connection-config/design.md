# Design

## Context

See proposal.md and issue #55. ApplicationFactory supplies a lazy EnvironmentEchoClientFactory to four service commands. That factory currently loads .env via Symfony Dotenv and builds ServiceFactory. Reference paths and storage policies come from bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00, docs/config.md.

## Decisions

- Use JSON schema version 1 with api_key, user_id and base_url. Validate UUIDs and an HTTP(S) URL locally with generic field errors, never credential values. Do not add profiles or a separate secret-file model.
- Add --config to the application's input definition. A console-specific configurable factory interface allows AbstractEchoCommand to pass the bound option without changing EchoClientFactoryInterface or parsing argv manually. Install receives the same resolver/store.
- Explicit --config selects only that file and bypasses dotenv connection loading. Otherwise retain current Dotenv loading and environment precedence. If either API key or user ID is nonempty, validate that complete legacy source; never fill missing credentials from a user file. If neither is present, read the default file. A base URL alone does not select an environment connection and does not override a saved file's endpoint.
- Use absolute XDG_CONFIG_HOME/rarus-echo/config.json, otherwise absolute HOME/.config/rarus-echo/config.json on Unix; APPDATA/rarus-echo/config.json on Windows. Explicit relative file paths use cwd. Reject empty paths and stream/remote URI paths. Resolve storage independently of executable/PHAR location.
- Resolve/read lazily when credentials are needed. Missing default file gives actionable install guidance while missing explicit file fails without fallback. Help/version/list/install do not require a readable existing connection.
- Install prompts for hidden API key, user ID and base URL, validates before writing, and requires interaction. Hidden input must not fall back to visible input. Existing files require explicit interactive overwrite confirmation with default No; noninteractive setup fails without creating files.
- Create new directories with private permissions and an exclusive temporary file beside the destination. Establish 0600 / current-user Windows ACL before writing secrets and flush before committing. First setup publishes via an atomic hard link that cannot clobber a concurrent writer; confirmed replacement uses atomic rename. Filesystems without hard-link support fail closed. Never chmod or replace unrelated existing parent directories, including directories created concurrently. Reject destination symlinks and nonregular files; verify file privacy on read. Windows uses inbox PowerShell by absolute SystemRoot path, passing paths as child environment data; errors expose no credentials.
- Unit tests verify install questions/validation, source precedence and storage behavior. Native acceptance creates a protected config through a probe-only scripted installer, uses it from a different cwd with a clean environment, tests explicit override and failures, and verifies Windows ACL behavior. Normal application artifacts expose only interactive install.

## Alternatives

- Environment-only setup does not meet standalone first-run requirements.
- Copying bitrix24-cli's full profile/credential-file model adds unrelated surface area.
- Reading raw argv in client factories risks inconsistent global-option parsing; bound command input is used instead.

## Risks / Trade-offs

- Plaintext credentials rely on OS user permissions; diagnostics never echo data.
- Existing broad-permission files are rejected with actionable storage guidance.
- Windows ACL verification needs inbox PowerShell. Missing/failed protection fails closed before secrets are written.
- Interactive hidden-input support comes from Symfony Console; headless setup uses the existing environment/.env path.
