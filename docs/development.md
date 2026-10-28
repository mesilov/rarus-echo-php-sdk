# Разработка и сопровождение

[Документация](README.md) · [Главная](../README.md)

Команды выполняются из корня checkout. [Сборка standalone](../build/README.md).

## Разработка

### Требования для разработки

- Docker & Docker Compose
- Make
- Node.js 20.19+ или 24+
- OpenSpec CLI 1.3.1 для OpenSpec workflow:
  ```bash
  npm install -g @fission-ai/openspec@1.3.1
  openspec --version
  ```

### Первоначальная настройка

```bash
make docker-init      # Инициализация Docker окружения и установка зависимостей
make docker-up        # Запуск контейнеров
make dev-php-bash     # Войти в контейнер
```

### Основные команды

```bash
make lint-all         # Запуск всех линтеров
make lint-openspec    # Проверка OpenSpec артефактов
make lint-agent-plugins # Проверка agent plugin и CLI reference
make lint-php         # Запуск PHP-линтеров
make lint-cs-fixer-fix # Исправление стиля кода
make lint-phpstan     # Статический анализ
make test-unit        # Юнит-тесты
make test-integration # Интеграционные тесты
make test-all         # Все тесты
make ci               # Полный CI pipeline локально
```

Полный список команд: `make help`

### Интеграционные тесты

Integration tests делают реальные API-запросы и загружают короткие аудиофайлы из `tests/Assets/ru/`. Для локального запуска добавьте credentials в `.env.local`; файл уже игнорируется Git:

```dotenv
RARUS_ECHO_API_KEY=your-api-key-uuid
RARUS_ECHO_USER_ID=your-user-id-uuid
RARUS_ECHO_BASE_URL=https://production-ai-ui-api.ai.rarus-cloud.ru
```

Если credentials не заданы или в `.env` остались placeholder-значения, integration tests будут пропущены.

```bash
make test-integration
make test-integration-core
make test-integration-queue
make test-integration-status
make test-integration-transcription
```

### Workflow поддержки

Поддержка проекта идет от GitHub issue к Pull Request в ветку `dev`.

1. Откройте или выберите issue и зафиксируйте ожидаемый результат.
2. Создайте ветку от `dev`: `feature/<issue>-<slug>`, `bugfix/<issue>-<slug>` или `docs/<issue>-<slug>`.
3. Для нетривиальных изменений публичного API, поведения SDK, архитектуры, CI или процесса поддержки создайте OpenSpec change в `openspec/changes/<change-id>/`.
4. Для опечаток, обновлений зависимостей и небольших документационных правок OpenSpec можно не использовать, если отдельная спецификация не добавляет ясности.
5. Перед PR запустите локальную проверку и откройте Pull Request в `dev`.
6. Считайте issue завершенным только после зеленого CI в Pull Request.

OpenSpec change обычно содержит:

- `proposal.md` - зачем нужно изменение и что меняется;
- `design.md` - технические решения, если они нужны;
- `specs/<capability>/spec.md` - требования и сценарии;
- `tasks.md` - чеклист реализации.

Основные команды для OpenSpec:

```bash
openspec list
openspec list --specs
make lint-openspec
```

OpenSpec CLI генерирует repo-local commands/skills для Claude Code и Codex. После обновления CLI синхронизируйте эти файлы командой:

```bash
openspec update --force
```

После merge связанного PR завершенный change архивируется командой:

```bash
openspec archive <change-id> --yes
```

Для agent-assisted поддержки используйте repo-local skill. Claude Code и Codex читают один общий русский skill через свои стандартные entrypoint-пути:

- Claude Code: `.claude/skills/rarus-echo-maintainer/SKILL.md`
- Codex: `.codex/skills/rarus-echo-maintainer/SKILL.md`

## Вклад в проект

Мы приветствуем вклад в развитие проекта! Пожалуйста, ознакомьтесь с [CONTRIBUTING.md](../CONTRIBUTING.md).

### Процесс разработки

1. Fork репозитория
2. Создайте feature branch от `dev`
3. Внесите изменения
4. Запустите тесты и линтеры: `make ci`
5. Создайте Pull Request в `dev`
