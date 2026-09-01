# RARUS Echo CLI Reference

This file is generated from current checkout structured CLI metadata.
It intentionally records only RARUS Echo command contracts and excludes framework-provided Symfony options.

Refresh it after CLI command or option changes:

```bash
.agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh
```

## Commands

| Command | Description |
| --- | --- |
| `queue` | Show aggregated transcription queue information. |
| `submit` | Submit one or more files for transcription. |
| `status` | Show transcription status for one file. |
| `transcript` | Show transcription result for one file. |

## queue

Show aggregated transcription queue information.

### Usage

```text
queue [--json]
```

### Arguments

None.

### Options

| Option | Accepts Value | Value Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- | --- |
| `--json` | no | no | no | `false` | Write command result as JSON. |

## submit

Submit one or more files for transcription.

### Usage

```text
submit [--json] [--task-type TASK-TYPE] [--language LANGUAGE] [--censor] [--speakers-correction] [--timestamps-extended] [--no-store-file] [--low-priority] [--request-source REQUEST-SOURCE] [--wait] [--poll-interval POLL-INTERVAL] [--timeout TIMEOUT] [--raw-result] [--output OUTPUT] [--] <files>...
```

### Arguments

| Argument | Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- |
| `files` | yes | yes | `[]` | File paths to submit. |

### Options

| Option | Accepts Value | Value Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- | --- |
| `--json` | no | no | no | `false` | Write command result as JSON. |
| `--task-type` | yes | yes | no | `"transcription"` | Task type: transcription, timestamps, diarization, raw_transcription |
| `--language` | yes | yes | no | `"auto"` | Language code: auto, ru, en, de, fr, es, pt, hy, ja, tr, ar, zh, he, vi |
| `--censor` | no | no | no | `false` | Enable censorship. |
| `--speakers-correction` | no | no | no | `false` | Enable speaker correction. |
| `--timestamps-extended` | no | no | no | `false` | Enable extended timestamps for diarization. |
| `--no-store-file` | no | no | no | `false` | Do not store submitted files after processing. |
| `--low-priority` | no | no | no | `false` | Submit with low processing priority. |
| `--request-source` | yes | yes | no | `null` | Optional request source header. |
| `--wait` | no | no | no | `false` | Poll until submitted transcript results reach a terminal state. |
| `--poll-interval` | yes | yes | no | `"30"` | Polling interval in seconds when using --wait. |
| `--timeout` | yes | yes | no | `"7200"` | Maximum wait time in seconds when using --wait. |
| `--raw-result` | no | no | no | `false` | With --wait, write only the single transcript result to stdout. |
| `--output` | yes | yes | no | `null` | With --wait, write the single transcript result to a file. |

## status

Show transcription status for one file.

### Usage

```text
status [--json] [--] <file-id>
```

### Arguments

| Argument | Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- |
| `file-id` | yes | no | `null` | RARUS Echo file UUID. |

### Options

| Option | Accepts Value | Value Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- | --- |
| `--json` | no | no | no | `false` | Write command result as JSON. |

## transcript

Show transcription result for one file.

### Usage

```text
transcript [--json] [--] <file-id>
```

### Arguments

| Argument | Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- |
| `file-id` | yes | no | `null` | RARUS Echo file UUID. |

### Options

| Option | Accepts Value | Value Required | Multiple | Default | Description |
| --- | --- | --- | --- | --- | --- |
| `--json` | no | no | no | `false` | Write command result as JSON. |
