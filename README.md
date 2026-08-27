# RARUS Echo PHP SDK

[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

PHP SDK для сервиса транскрибации RARUS Echo с использованием стандартов PSR и компонентов Symfony.

## Статус проекта

**beta** - SDK покрывает текущую версию API.

## Возможности

- Асинхронная транскрибация аудио и видео файлов
- Поддержка 13 языков и автоопределения языка
- Различные типы транскрибации (обычная, с метками времени, с диаризацией)
- PSR-совместимость (PSR-3, PSR-7, PSR-17, PSR-18)
- Автоматическое обнаружение HTTP клиента (php-http/discovery)

## Требования

- PHP 8.4 или 8.5
- Composer 2.x
- Расширения: json, curl, mbstring, fileinfo

## Установка

```bash
composer require mesilov/rarus-echo-php-sdk
```

## Быстрый старт

### CLI через Docker image

Самый короткий happy-path не требует локальной установки PHP-пакета: запустите CLI из готового Docker image. Используйте `--pull=always`, если нужен актуальный опубликованный image, а не локально закешированный tag.

```bash
docker run --pull=always --rm ghcr.io/mesilov/rarus-echo-php-sdk:cli
```

Image использует `rarus-echo` как entrypoint, поэтому команды передаются сразу после имени image:

```bash
docker run --pull=always --rm \
  -e RARUS_ECHO_API_KEY=your-api-key-uuid \
  -e RARUS_ECHO_USER_ID=your-user-id-uuid \
  ghcr.io/mesilov/rarus-echo-php-sdk:cli queue --json

docker run --pull=always --rm \
  -e RARUS_ECHO_API_KEY=your-api-key-uuid \
  -e RARUS_ECHO_USER_ID=your-user-id-uuid \
  -v "$PWD/audio.ogg:/audio.ogg:ro" \
  ghcr.io/mesilov/rarus-echo-php-sdk:cli submit /audio.ogg --language=ru --json
```

GitHub Actions собирает image для `linux/amd64` и `linux/arm64`, проверяет сборку в pull request и публикует `ghcr.io/mesilov/rarus-echo-php-sdk:cli` при изменениях в `dev`, `main` или ручном запуске workflow.

### PHP SDK

```php
<?php

declare(strict_types=1);

use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Core\Credentials;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

// Создание credentials
$credentials = Credentials::fromString(
    apiKey: 'your-api-key-uuid',
    userId: 'your-user-id-uuid'
);

// Инициализация SDK
$factory = new ServiceFactory($credentials);

// Настройка опций транскрибации
$options = TranscriptionOptions::create()
    ->withTaskType(TaskType::DIARIZATION)  // С разбиением по говорящим
    ->withLanguage(Language::RU)            // Русский язык
    ->withCensor(true)                      // С цензурой
    ->build();

// Отправка файла на транскрибацию
$result = $factory->getTranscriptionService()->submit(
    files: ['/path/to/audio.mp3'],
    transcriptionOptions: $options
);

$fileIds = $result->getFileIds();
$fileId = $fileIds[0]; // Uuid объект
echo "Файл отправлен: {$fileId->toRfc4122()}\n";

// Проверка статуса
$status = $factory->getStatusService()->getByFileId($fileId);
echo "Статус: {$status->transcriptionStatus->value}\n";

// Получение результата после завершения
if ($status->isSuccessful()) {
    $transcript = $factory->getTranscriptionService()->getByFileId($fileId);
    echo "Результат:\n{$transcript->result}\n";
}
```

### С обработкой ошибок

```php
<?php

declare(strict_types=1);

use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\ApiException;

try {
    $result = $factory->getTranscriptionService()->submit($files, $options);
} catch (FileException $e) {
    // Ошибка файла (не найден, не читается, неверный формат)
    echo "Ошибка файла: {$e->getMessage()}\n";
} catch (ValidationException $e) {
    // Ошибка валидации (422)
    echo "Ошибка валидации: {$e->getMessage()}\n";
} catch (AuthenticationException $e) {
    // Ошибка аутентификации (401)
    echo "Ошибка аутентификации: {$e->getMessage()}\n";
} catch (ApiException $e) {
    // Общая ошибка API
    echo "Ошибка API: {$e->getMessage()}\n";
}
```

## CLI

После установки через Composer доступен исполняемый файл:

```bash
vendor/bin/rarus-echo --help
```

CLI использует те же credentials, что и SDK:

```bash
export RARUS_ECHO_API_KEY=your-api-key-uuid
export RARUS_ECHO_USER_ID=your-user-id-uuid
export RARUS_ECHO_BASE_URL=https://production-ai-ui-api.ai.rarus-cloud.ru # опционально
```

Если в текущей рабочей директории есть `.env`, CLI загрузит значения из него перед выполнением сервисной команды.

### Команды

```bash
vendor/bin/rarus-echo queue
vendor/bin/rarus-echo status 11111111-1111-1111-1111-111111111111
vendor/bin/rarus-echo transcript 11111111-1111-1111-1111-111111111111
vendor/bin/rarus-echo submit /path/to/audio.ogg --task-type=diarization --language=ru
```

Для автоматизации добавьте `--json`:

```bash
vendor/bin/rarus-echo queue --json
vendor/bin/rarus-echo submit /path/to/audio.ogg --json
```

`submit` поддерживает опции `--task-type`, `--language`, `--censor`, `--speakers-correction`, `--no-store-file`, `--low-priority` и `--request-source`. Основной результат пишется в stdout, ошибки пишутся в stderr, успешные команды завершаются с кодом `0`.

## Примеры PHP SDK

### Очередь транскрибации

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Rarus\Echo\Services\ServiceFactory;

$factory = ServiceFactory::fromEnvironment();
$queue = $factory->getQueueService()->getQueueInfo();

printf(
    "В очереди: %d файлов, %d MB, %d минут\n",
    $queue->filesCount,
    $queue->filesSize,
    $queue->filesDuration
);
```

### Отправка файла и проверка статуса

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

$factory = ServiceFactory::fromEnvironment();

$options = TranscriptionOptions::create()
    ->withTaskType(TaskType::DIARIZATION)
    ->withLanguage(Language::RU)
    ->build();

$submitResult = $factory->getTranscriptionService()->submit(
    files: ['/path/to/audio.ogg'],
    transcriptionOptions: $options
);

$fileId = $submitResult->getFileIds()[0];
$status = $factory->getStatusService()->getByFileId($fileId);

printf(
    "file_id=%s status=%s\n",
    $fileId->toRfc4122(),
    $status->transcriptionStatus->value
);
```

### Проверка списка статусов

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Services\ServiceFactory;
use Symfony\Component\Uid\Uuid;

$factory = ServiceFactory::fromEnvironment();
$fileIds = [
    Uuid::fromString('11111111-1111-1111-1111-111111111111'),
    Uuid::fromString('22222222-2222-2222-2222-222222222222'),
];

$statusList = $factory->getStatusService()->getList(
    fileIds: $fileIds,
    pagination: new Pagination(page: 1, perPage: 10)
);

foreach ($statusList->getResults() as $status) {
    printf(
        "file_id=%s status=%s\n",
        $status->fileId->toRfc4122(),
        $status->transcriptionStatus->value
    );
}

printf(
    "page=%d per_page=%d total_pages=%d\n",
    $statusList->pagination->page,
    $statusList->pagination->perPage,
    $statusList->pagination->total
);
```

### Получение результата

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Rarus\Echo\Services\ServiceFactory;
use Symfony\Component\Uid\Uuid;

$factory = ServiceFactory::fromEnvironment();
$fileId = Uuid::fromString('11111111-1111-1111-1111-111111111111');

$transcript = $factory->getTranscriptionService()->getByFileId($fileId);

if ($transcript->isSuccessful()) {
    echo $transcript->result ?? '';
}
```

## Поддерживаемые возможности API

### Типы транскрибации
- `transcription` - обычная транскрипция
- `timestamps` - с метками времени
- `diarization` - с разбиением по говорящим
- `raw_transcription` - сырой текст

### Языки
`ru`, `en`, `de`, `fr`, `es`, `pt`, `hy`, `ja`, `tr`, `ar`, `zh`, `he`, `vi`, `auto`

### Статусы
- `waiting` - ожидает в очереди
- `processing` - обрабатывается
- `success` - завершено успешно
- `failure` - ошибка

## Документация

- [OpenAPI спецификация](https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json) - официальная API документация

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
make php-cli-bash     # Войти в контейнер
```

### Основные команды

```bash
make lint-all         # Запуск всех линтеров
make lint-openspec    # Проверка OpenSpec артефактов
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

Мы приветствуем вклад в развитие проекта! Пожалуйста, ознакомьтесь с [CONTRIBUTING.md](CONTRIBUTING.md).

### Процесс разработки

1. Fork репозитория
2. Создайте feature branch от `dev`
3. Внесите изменения
4. Запустите тесты и линтеры: `make ci`
5. Создайте Pull Request в `dev`

## Лицензия

MIT License. См. [LICENSE](LICENSE) для деталей.

## Поддержка

Если у вас возникли вопросы или проблемы, пожалуйста, создайте [Issue](../../issues).
