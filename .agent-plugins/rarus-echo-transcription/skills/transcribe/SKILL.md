---
name: transcribe
description: Submit audio or video files to RARUS Echo, inspect queue state, check transcription status, and fetch transcripts through the existing rarus-echo CLI while avoiding credential leaks.
---

# RARUS Echo Transcription

Use this skill when the user asks to run a RARUS Echo transcription workflow from an agent session.

## Inputs

Accept free-form invocation text such as:

```text
transcribe downloads/audio.ogg --language=ru --task-type=diarization --speakers-correction
queue
status 11111111-1111-1111-1111-111111111111
transcript 11111111-1111-1111-1111-111111111111
```

If the requested workflow lacks the required audio path or file id, ask one concise question before running commands.

## References

- Read `references/cli.md` when exact command names, options, defaults, or output modes matter.
- Read `references/distribution.md` for installation, packaging, marketplace, or publication questions.
- Run `scripts/update-cli-reference.sh` from the plugin root repository when CLI help changes.

## Safety Rules

- Never print API keys, user ids, env-file contents, or command lines containing literal credential values.
- Prefer passing existing environment variables by name, for example `-e RARUS_ECHO_API_KEY`, instead of embedding values.
- Use `--env-file` only with an existing file path; do not display file contents.
- Mount local input files read-only when using Docker.
- Report non-terminal `waiting` or `processing` states as still in progress.
- Do not submit live audio unless the user asked for submission and credentials are available.

## Command Selection

Default to the published Docker CLI. Run it directly — do NOT probe for host PHP, an installed `vendor/`, or a local binary first:

```bash
docker run --rm ghcr.io/mesilov/rarus-echo-php-sdk:cli <command> [args]
```

Use `--pull=always` only when the user asks to refresh the image, when validating against the latest published image, or when diagnosing a suspected stale local tag.

Use a local binary only as an explicit opt-in — when the user asks for local execution, or when Docker is unavailable. It requires installed dependencies and a host PHP that satisfies the SDK requirement:

```bash
vendor/bin/rarus-echo <command> [args]
```

If the repository checkout has dependencies and compatible host PHP but no Composer bin proxy, `php bin/rarus-echo <command> [args]` is an acceptable local fallback.

## Docker Planning

For Docker submissions:

1. Resolve every input audio path to a host path.
2. Mount each input file read-only into the container, for example:

   ```bash
   -v "$PWD/downloads/audio.ogg:/input/audio.ogg:ro"
   ```

3. Pass credentials safely:

   ```bash
   -e RARUS_ECHO_API_KEY -e RARUS_ECHO_USER_ID -e RARUS_ECHO_BASE_URL
   ```

   or:

   ```bash
   --env-file .env --env-file .env.local
   ```

4. Use the mounted container paths in `submit`.
5. Include `--json` for machine-readable output unless the user requested raw transcript text.

When showing a command to the user, redact secret-bearing parts or describe them structurally, for example `-e RARUS_ECHO_API_KEY` rather than `-e RARUS_ECHO_API_KEY=<value>`.

## Workflows

Queue inspection:

```bash
rarus-echo queue --json
```

Report `files_count`, `files_size`, and `files_duration`.

Submit one or more local files:

```bash
rarus-echo submit /input/audio.ogg --language=ru --task-type=diarization --speakers-correction --json
```

Parse and return every `file_id`. If multiple files were submitted, preserve input order when explaining which id belongs to which file.

Submit and wait for final output:

```bash
rarus-echo submit /input/audio.ogg --language=ru --wait --json
```

Progress is written to stderr by the CLI. Treat stdout as the parseable result. For raw transcript output, use `--raw-result` or redirect stdout only after confirming the user wants a text artifact.

Status lookup:

```bash
rarus-echo status <file-id> --json
```

Report the status exactly. Do not claim completion unless the status is terminal.

Transcript retrieval:

```bash
rarus-echo transcript <file-id> --json
```

If the response indicates the transcript is not ready, report that state and offer the next polling command. If it is ready, summarize metadata and provide the transcript or the requested output file.

## Verification

For repository maintenance work on this skill, run:

```bash
make lint-agent-plugins
```

This validates plugin manifests, marketplace JSON, skill frontmatter, required references, and drift between `references/cli.md` and current CLI help.
