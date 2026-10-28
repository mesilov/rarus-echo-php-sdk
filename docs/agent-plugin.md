# Agent plugin для транскрибации

[Документация](README.md) · [Главная](../README.md)

Команды с относительными путями выполняйте из корня checkout репозитория. CLI нужен отдельно: [варианты установки](install/README.md).

## Установка и использование

В репозитории есть skills-only plugin `rarus-echo-transcription` для Claude Code и Codex-compatible hosts. Он работает только через установленный `rarus-echo`, предлагает установку при его отсутствии и описывает безопасный workflow: проверить очередь, отправить один или несколько локальных аудиофайлов, получить `file_id`, проверить статус, дождаться результата через `submit --wait` и забрать transcript без вывода credentials.

Исходники plugin:

```text
.agent-plugins/rarus-echo-transcription/
```

Claude Code может загрузить plugin напрямую из checkout на одну сессию:

```bash
claude --plugin-dir ./.agent-plugins/rarus-echo-transcription
```

Или поставить через repo-local marketplace из корня репозитория:

```bash
claude plugin marketplace add ./ --scope user
claude plugin install rarus-echo-transcription@rarus-echo-plugins
```

После установки в Claude Code namespaced invocation выглядит так:

```text
/rarus-echo-transcription:transcribe downloads/audio.ogg --language=ru --task-type=diarization --speakers-correction
```

Repo-local marketplace files:

```text
.claude-plugin/marketplace.json
.agents/plugins/marketplace.json
```

Codex-compatible hosts читают общий skill из `skills/transcribe/SKILL.md`; точный синтаксис invocation зависит от host и использует имя skill, например:

```bash
codex plugin marketplace add .
codex plugin add rarus-echo-transcription@rarus-echo-plugins
```

```text
$transcribe downloads/audio.ogg --language=ru --task-type=diarization --speakers-correction
```

Карта вызовов для целевого релиза 0.5.0 (проверенная основа — 0.5.0-rc-3) находится в `.agent-plugins/rarus-echo-transcription/skills/transcribe/references/cli.md`; установка CLI и настройка подключения описаны рядом в `installation.md`. CLI reference генерируется из structured metadata текущего CLI и проверяется на drift, включая новые команды и опции вне разрешённых списков. Проверка фиксирует только project-owned команды и опции, без framework-provided Symfony options:

```bash
.agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh
make lint-agent-plugins
```
