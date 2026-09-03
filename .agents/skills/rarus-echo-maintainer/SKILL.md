---
name: rarus-echo-maintainer
description: "Используй, когда работаешь с GitHub issues, процессом сопровождения, OpenSpec, ветками, pull requests или CI для mesilov/rarus-echo-php-sdk."
user-invocable: true
---

# Сопровождение RARUS Echo

Репозиторий: `mesilov/rarus-echo-php-sdk`

Используй этот скилл для сопровождения репозитория через GitHub issue: чтение задачи, планирование реализации, решение о необходимости OpenSpec, создание ветки, запуск проверок, открытие pull request и контроль CI.

## Начало работы

1. Перед реализацией загрузи GitHub issue. Прочитай заголовок, описание, labels, milestone, assignees, комментарии и связанные pull requests.
2. Из корня репозитория проверь текущее состояние:
   ```bash
   pwd
   git status --short --branch
   openspec list
   openspec list --specs
   ```
3. Сохраняй несвязанные локальные изменения. Не сбрасывай, не удаляй, не добавляй в индекс и не форматируй файлы вне области issue.
4. Используй `dev` как базовую ветку для работы по issue, если пользователь явно не указал другое.
5. Создай подготовленный per-issue worktree обвязкой. Она базирует ветку на `origin/<base>` (по умолчанию `dev`), поэтому локальную `dev` двигать не нужно:
   ```bash
   make worktree-new ISSUE=<issue-number> SLUG=<short-slug> [TYPE=feature|bugfix|docs] [BASE=dev]
   cd .worktree/<issue-number>-<short-slug>
   ```
   Обвязка создаёт worktree в `.worktree/<issue-number>-<short-slug>` (папка в `.gitignore`) и сама:
   - делает `git fetch origin <base>` и создаёт ветку `<type>/<issue-number>-<short-slug>` от `origin/<base>`;
   - симлинкует `.env.local` из основного worktree (единый источник секретов);
   - клон-копирует `vendor/` из основного worktree (или делает `make composer-install`, если vendor отсутствует).

   Соглашение об именах ветки по типу issue:
   ```text
   feature/<issue-number>-<short-slug>
   bugfix/<issue-number>-<short-slug>
   docs/<issue-number>-<short-slug>
   ```
   Активные worktree смотри через `make worktree-list`. Если создаёшь worktree вручную без обвязки, базируй ветку на `origin/dev` и не двигай локальную `dev` без ff-проверки (`git fetch origin dev` + `git merge-base --is-ancestor dev origin/dev`).

## Политика OpenSpec

Создавай или обновляй OpenSpec change для:

- изменений публичного SDK API;
- поведения, видимого пользователям SDK;
- изменений архитектуры или сервисного слоя;
- изменений CI, процесса поддержки или процесса сопровождения.

OpenSpec можно пропустить для исправления опечаток, обновления зависимостей, механического форматирования и тривиальных правок документации в одном файле.

Когда OpenSpec обязателен:

1. Проверь пересекающиеся активные изменения через `openspec list`.
2. Создай или продолжи `openspec/changes/<change-id>/`.
3. Синхронизируй `proposal.md`, `design.md` при необходимости, `tasks.md` и дельты спецификаций с реализацией.
4. Проверь:
   ```bash
   make lint-openspec
   ```
5. Архивируй завершенные изменения только после merge соответствующего pull request:
   ```bash
   openspec archive <change-id> --yes
   ```

## Правила реализации

- Следуй существующей структуре SDK: `Services`, `Core`, `Infrastructure`, `Contracts`, immutable result/configuration objects, strict types и PSR-compatible dependencies.
- Держи изменения в рамках issue.
- Добавляй или обновляй тесты, когда меняется runtime-поведение PHP-кода.
- Обновляй `README.md`, `CONTRIBUTING.md` или артефакты OpenSpec, когда меняется процесс или публичное использование.
- Для каждой issue-работы обязательно обновляй `CHANGELOG.md`: добавляй запись в раздел `Unreleased` с кратким описанием изменения.
- При изменении CLI (команды или опции в `src/Infrastructure/Console/Command/`) выравнивай документацию в двух местах — CLI reference transcription-скилла и `README.md`. Подробности в разделе «Изменения CLI».
- Не добавляй правила, специфичные для Bitrix24, generated result-item contracts, OpenAPI refresh steps или выбор веток v1/v3. Это относится к другим SDK, не к этому репозиторию.

## Изменения CLI

Когда меняются CLI-команды или их опции (`src/Infrastructure/Console/Command/`), обязательно выровняй документацию в двух местах, иначе она разойдётся с фактическим контрактом команд:

1. Сгенерированный CLI reference transcription-скилла. Перегенерируй его из текущего чекаута:
   ```bash
   .agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh
   ```
   Дрейф этого файла (`.agent-plugins/rarus-echo-transcription/skills/transcribe/references/cli.md`) проверяется `make lint-agent-plugins` (входит в `make lint-all` и `make ci`): если reference устарел, проверка падает.

   Важно: генератор работает по двум спискам в `update-cli-reference.sh`:
   - `PROJECT_COMMANDS` (bash) — единый источник списка команд (node-часть берёт его из аргументов, отдельного дублирующего массива нет);
   - `optionAllowlist` (node) — разрешённые опции по каждой команде.

   Поэтому при добавлении новой команды внеси её в `PROJECT_COMMANDS` и заведи для неё запись в `optionAllowlist` (минимум `["json"]`, так как `--json` есть у всех команд) — без записи генератор упадёт с понятной ошибкой. При добавлении новой опции внеси её в `optionAllowlist` соответствующей команды. Ключ, не добавленный в `optionAllowlist`, не попадёт в reference, а `make lint-agent-plugins` всё равно пройдёт — останется недокументированным без ошибки.
2. `README.md`, раздел `## CLI` (включая «Справочник команд и опций»). Он правится вручную и не покрыт дрейф-валидацией, поэтому актуализируй список команд, аргументов, опций, значений по умолчанию и примеры сам.

Перед PR убедись, что оба места согласованы с определениями команд.

## Выкатка релиза

Для release issue. Milestone версии используй только как источник целевой версии, если сама issue является release issue:

1. Определи целевую SemVer-версию из issue body, title или milestone.
2. Если issue и milestone указывают разные версии, остановись и согласуй целевую версию.
3. Обнови `CHANGELOG.md`: перенеси текущие записи из `Unreleased` в секцию `## [<version>] - YYYY-MM-DD`.
4. Сохрани новый пустой раздел `## [Unreleased]` выше релизной секции.
5. Обнови пример установки в `README.md` под текущую release line, например:
   ```bash
   composer require mesilov/rarus-echo-php-sdk:^0.4
   ```
6. После merge release PR создай tag/GitHub release/Packagist-публикацию только если это требуется конкретной issue.

## Проверка

Для OpenSpec или изменений только процесса запускай:

```bash
make lint-openspec
git diff --check
make test-unit
make lint-all
```

Для изменений PHP-поведения дополнительно запускай точечные unit-тесты, покрывающие измененный код. Integration tests запускай только когда issue затрагивает поведение реального API и доступны учетные данные:

```bash
make test-integration
```

Если обязательную проверку нельзя запустить из-за отсутствующей инфраструктуры или учетных данных, укажи точный блокер и не описывай issue как завершенную.

## Pull Request

Открывай pull request только после зеленой локальной проверки или после явно задокументированного внешнего блокера.

Правила:

- Отправь issue branch в `origin`.
- Открой pull request в `dev`.
- Добавь `Closes #<issue-number>` в тело PR как обычный текст.
- Укажи команды проверки, которые прошли.
- Проверь PR CI после открытия или обновления PR.
- Проверь inline review threads и top-level comments от agent reviewers: Codex, Claude и других review bots.
- Для каждого agent comment проверь обратную связь по текущему коду, внеси корректную правку или ответь технической причиной, если комментарий устарел или неприменим.
- Резолвь review thread только после правки, устаревания комментария или ответа с причиной, почему изменение не требуется.
- Не сообщай, что issue завершена, пока обязательные CI checks не стали зелеными и agent review threads не обработаны.

## Очистка worktree

После merge pull request удали per-issue worktree обвязкой из основного worktree:

```bash
make worktree-remove ISSUE=<issue-number>
```

Worktree и его скопированный `vendor/` со ссылкой на `.env.local` удаляются, локальная ветка сохраняется (удали её вручную, когда она больше не нужна). При незакоммиченных изменениях, которые точно можно потерять, используй `FORCE=1`. Целевой worktree можно указать явно через `NAME=<issue-number>-<short-slug>`.
