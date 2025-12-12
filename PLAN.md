# План реализации RARUS Echo PHP SDK

## Обзор

PHP SDK для сервиса транскрибации RARUS Echo с использованием стандартов PSR, библиотеки php-http/discovery и компонентов Symfony для работы с сетью.

**API документация:** https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json

## Технологический стек

### Основные технологии
- **PHP:** 8.2, 8.3, 8.4
- **Архитектура:** Многослойная архитектура по аналогии с bitrix24/b24phpsdk
- **HTTP:** PSR-18, HTTPlug, Symfony HttpClient
- **Сериализация:** Symfony Serializer
- **Файловая система:** Symfony Filesystem

### PSR стандарты
- **PSR-3:** Logger interface
- **PSR-4:** Autoloading
- **PSR-7:** HTTP message interfaces
- **PSR-12:** Coding style
- **PSR-17:** HTTP factories
- **PSR-18:** HTTP client interface

### Symfony компоненты
- `symfony/http-client` - HTTP клиент
- `symfony/serializer` - Сериализация данных
- `symfony/property-access` - Доступ к свойствам
- `symfony/property-info` - Информация о свойствах
- `symfony/mime` - Работа с MIME типами
- `symfony/filesystem` - Работа с файловой системой
- `symfony/validator` - Валидация данных

### Работа с датами
- `nesbot/carbon` - Мощная библиотека для работы с датами и временем (опциональная)
- Использование стандартного `DateTimeInterface` в API сервисов
- Carbon остается в зависимостях для удобства пользователей

### HTTP Discovery
- `php-http/discovery` - Автоматическое обнаружение HTTP клиентов
- `php-http/httplug` - HTTP клиент абстракция
- `php-http/message` - HTTP сообщения

### Инструменты разработки
- **Docker:** Контейнеризация разработки
- **Makefile:** Автоматизация команд
- **PHPUnit:** Тестирование
- **PHPStan:** Статический анализ (level 8)
- **PHP CS Fixer:** Форматирование кода (PSR-12)
- **Rector:** Модернизация кода
- **GitHub Actions:** CI/CD

## Архитектура проекта

### Слои архитектуры

```
Application Layer (Точка входа)
    ↓
Services Layer (Бизнес-логика)
    ↓
Core Layer (API клиент)
    ↓
Infrastructure Layer (HTTP, Serializer, Filesystem)
```

### Структура директорий

```
rarus-echo-php-sdk/
├── .github/workflows/        # CI/CD конфигурация
├── docker/php-cli/           # Docker окружение
├── docs/                     # Документация
├── src/
│   ├── Application/          # Точка входа, контракты
│   ├── Core/                 # API клиент, базовые классы
│   ├── Services/             # Сервисы (Transcription, Status, Queue)
│   ├── Infrastructure/       # HTTP, Serializer, Filesystem
│   ├── Enum/                 # Перечисления
│   └── Exception/            # Исключения
├── tests/
│   ├── Unit/                 # Юнит тесты
│   ├── Integration/          # Интеграционные тесты
│   ├── Fixtures/             # Фикстуры для тестов
│   └── CustomAssertions/     # Собственные assertion'ы
├── tools/                    # Утилиты (генерация документации)
├── docker-compose.yaml       # Docker Compose конфигурация
├── Makefile                  # Make команды
└── composer.json             # Зависимости
```

## API сервиса транскрибации

### Эндпоинты

#### Transcription API
- `POST /v1/async/transcription` - Отправка файла на транскрибацию
- `GET /v1/async/transcription` - Получение результата транскрибации
- `GET /v1/async/transcription/period` - Получение транскриптов за период
- `POST /v2/async/transcription/list` - Получение списка транскриптов
- `POST /v2/webdav` - Загрузка из Rarus Drive

#### Status API
- `GET /v1/async/transcription/fileid` - Статус конкретного файла
- `GET /v1/async/transcription/userid` - Статусы всех файлов пользователя
- `POST /v2/async/transcription/fileid/list` - Список статусов

#### Queue API
- `GET /v1/async/transcription/queue` - Информация об очереди

### Типы транскрибации

- **transcription** - обычная транскрипция (по умолчанию)
- **timestamps** - транскрипция с метками времени
- **diarization** - транскрипция с диаризацией (разбиение по говорящим)
- **raw_transcription** - сырой текст транскрипции

### Поддерживаемые языки

`ru`, `en`, `de`, `fr`, `es`, `pt`, `hy`, `ja`, `tr`, `ar`, `zh`, `he`, `vi`, `auto` (автоопределение)

### Статусы транскрибации

- **waiting** - ожидает выполнения в очереди
- **processing** - файл обрабатывается
- **success** - транскрипция выполнена успешно
- **failure** - ошибка при выполнении

## Компоненты SDK

### 1. Application Layer

#### EchoApplication
Главная точка входа в SDK. Предоставляет доступ ко всем сервисам.

```php
$factory = new ServiceFactory($credentials);
$transcription = $app->getTranscriptionService();
$status = $app->getStatusService();
$queue = $app->getQueueService();
```

#### Contracts (Интерфейсы)
- `TranscriptionServiceInterface`
- `StatusServiceInterface`
- `QueueServiceInterface`

### 2. Core Layer

#### ApiClient
Базовый HTTP клиент для взаимодействия с API.

**Возможности:**
- Авторизация через API-ключ
- Retry механизм при сетевых ошибках
- Логирование запросов (PSR-3)
- Обработка ошибок API

#### Credentials
Хранение учетных данных:
- API ключ (обязательно)
- User ID (UUID пользователя)
- Base URL (по умолчанию: production)

#### Pagination
Immutable value object для управления пагинацией:
- `page` - номер страницы (1-based)
- `perPage` - количество элементов на странице

**Фабрики:**
- `Pagination::default()` - страница 1, 10 элементов
- `Pagination::firstPage(int $perPage)` - первая страница с заданным размером
- `Pagination::create(int $page, int $perPage)` - произвольные значения

**Хелперы:**
- `getOffset()`, `getLimit()` - для БД/API запросов
- `next()`, `previous()` - навигация по страницам
- `withPage()`, `withPerPage()` - создание новых объектов
- `toQueryParams()` - преобразование в массив параметров

#### Response Handler
Обработка ответов API, преобразование в Result объекты.

### 3. Services Layer

#### TranscriptionService

**Методы:**
- `submitTranscription(array $files, TranscriptionOptions $options): TranscriptPostResult`
- `getTranscript(string $fileId): TranscriptItemResult`
- `getTranscriptsByPeriod(DateTimeInterface $startDate, DateTimeInterface $endDate, Pagination $pagination): TranscriptBatchResult`
- `getTranscriptsList(array $fileIds, Pagination $pagination): TranscriptBatchResult`

#### StatusService

**Методы:**
- `getFileStatus(string $fileId): StatusItemResult`
- `getUserStatuses(DateTimeInterface $startDate, DateTimeInterface $endDate, Pagination $pagination): StatusBatchResult`
- `getStatusList(array $fileIds, Pagination $pagination): StatusBatchResult`

#### QueueService

**Методы:**
- `getQueueInfo(): QueueInfoResult`

### 4. Infrastructure Layer

#### HttpClient (PSR-18)
- Использование `Psr\Http\Client\ClientInterface`
- Auto-discovery через `php-http/discovery`
- Поддержка Symfony HttpClient, Guzzle, cURL
- Middleware для авторизации, retry, логирования

#### Serializer
- Symfony Serializer для JSON
- Нормализация/денормализация моделей
- Обработка multipart/form-data
- Валидация через Symfony Validator

#### Filesystem (Symfony Filesystem)
- **FileHelper**: Проверка файлов, копирование, temp файлы
- **FileValidator**: Валидация размера, MIME типов, доступности
- **FileUploader**: Подготовка файлов для multipart загрузки

**Преимущества:**
- Кроссплатформенность
- Безопасная работа с путями
- Atomic операции
- Правильная обработка permissions

### 5. Enums (PHP 8.2+)

```php
enum TaskType: string {
    case TRANSCRIPTION = 'transcription';
    case TIMESTAMPS = 'timestamps';
    case DIARIZATION = 'diarization';
    case RAW_TRANSCRIPTION = 'raw_transcription';
}

enum Language: string {
    case AUTO = 'auto';
    case RU = 'ru';
    case EN = 'en';
    // ... остальные языки
}

enum TranscriptionStatus: string {
    case WAITING = 'waiting';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
```

### 6. Exception Hierarchy

```
EchoException (базовое)
├── ApiException (400, 500)
├── ValidationException (422)
├── AuthenticationException (403)
├── NetworkException (connection errors)
└── FileException (file operations)
```

**Обработка ошибок API:**
- 400: Ошибка данных
- 403: Неверный API ключ
- 422: Валидация с детальным описанием полей
- 500: Внутренняя ошибка сервера

## Docker окружение

### Структура

```
docker/
└── php-cli/
    ├── Dockerfile
    └── conf.d/
        └── php.ini
```

### Dockerfile

**Multi-stage build:**
1. `php-extension-installer` - установщик расширений
2. `composer` - Composer 2.8
3. `dev-php` - PHP 8.3 CLI (финальный образ)

**Установленные расширения:**
- bcmath, intl, pcntl, opcache
- yaml, zip, curl
- mbstring, xml, dom, fileinfo

**Конфигурация:**
- PHP 8.3 CLI на Debian Bookworm
- Пользователь: www-data (UID/GID настраиваемые)
- Composer cache: `/tmp/composer/cache`

### docker-compose.yaml

**Сервисы:**
- `php-cli` - основной контейнер для разработки

**Особенности:**
- Volume mapping текущей директории
- Настройка переменных окружения (API ключ, User ID)
- Изолированная сеть

## Makefile - автоматизация команд

### Docker команды
```bash
make docker-init          # Первоначальная настройка
make docker-up            # Запуск контейнеров
make docker-down          # Остановка контейнеров
make docker-restart       # Перезапуск
make docker-rebuild       # Пересборка образов
```

### Composer команды
```bash
make composer-install     # Установка зависимостей
make composer-update      # Обновление зависимостей
make composer cmd="..."   # Произвольная команда composer
```

### Линтеры
```bash
make lint-all            # Все линтеры
make lint-cs-fixer       # Проверка стиля кода
make lint-cs-fixer-fix   # Исправление стиля
make lint-phpstan        # Статический анализ
make lint-rector         # Проверка Rector
make lint-rector-fix     # Применить Rector
```

### Тестирование
```bash
make test-unit                        # Юнит тесты
make test-integration                 # Интеграционные тесты
make test-all                         # Все тесты
make test-coverage                    # Coverage отчет
make test-integration-transcription   # Тесты транскрибации
make test-integration-status          # Тесты статусов
make test-integration-queue           # Тесты очереди
```

### CI/CD симуляция
```bash
make ci           # Полный CI pipeline
make pre-commit   # Проверки перед коммитом
```

### Разработка
```bash
make php-cli-bash      # Войти в контейнер
make php-cli-root      # Войти как root
make clear-cache       # Очистить кеш
make docs-generate     # Генерация документации
```

## Инструменты качества кода

### PHPStan (level 8)

**Конфигурация:** `phpstan.neon.dist`

**Особенности:**
- Максимальный уровень строгости
- Параллельная обработка (8 процессов)
- PHPDoc типы не считаются абсолютными
- Интеграция с PHPStorm
- Исключение fixtures и temp файлов

### PHP CS Fixer (PSR-12)

**Конфигурация:** `.php-cs-fixer.dist.php`

**Правила:**
- PSR-12 стандарт
- PHP 8.3 совместимость
- Strict types declaration
- Упорядоченные импорты
- Удаление неиспользуемых use
- Trailing commas в массивах

### Rector

**Конфигурация:** `rector.php`

**Наборы правил:**
- PHP 8.3 модернизация
- CODE_QUALITY
- DEAD_CODE
- TYPE_DECLARATION
- PRIVATIZATION
- NAMING
- EARLY_RETURN

### PHPUnit

**Конфигурация:** `phpunit.xml.dist`

**Test suites:**
- `unit` - юнит тесты
- `integration` - интеграционные тесты

**Coverage:**
- HTML отчеты
- Text output
- Минимальный coverage: 80%

## CI/CD (GitHub Actions)

### Workflows

#### tests.yml
- Матрица PHP версий: 8.2, 8.3, 8.4
- Валидация composer.json
- Установка зависимостей
- Запуск unit тестов
- PHPStan анализ
- Проверка стиля кода

#### code-quality.yml
- PHP CS Fixer
- PHPStan
- Rector (dry-run)

#### security.yml
- Проверка зависимостей
- Security advisories

## Этапы реализации

### Фаза 0: Инфраструктура и DevOps ✓
**Задачи:**
- [x] Создание структуры проекта
- [x] Настройка Docker окружения
- [x] Создание Makefile
- [x] Настройка линтеров (PHP CS Fixer, PHPStan, Rector)
- [x] Настройка PHPUnit
- [x] Настройка GitHub Actions
- [x] Создание composer.json с зависимостями

**Результат:** Готовое окружение для разработки

---

### Фаза 1: Core инфраструктура
**Задачи:**
- [ ] Создание базовых классов
  - [ ] `Credentials` - хранение учетных данных
  - [ ] `CredentialsBuilder` - builder для credentials
  - [ ] `ApiClient` - базовый HTTP клиент
  - [ ] `Response` - обертка над HTTP ответами
  - [ ] `ResponseHandler` - обработка ответов
- [ ] HTTP клиент с PSR-18
  - [ ] `HttpClientInterface`
  - [ ] `PsrHttpClient` - реализация через PSR-18
  - [ ] `AuthMiddleware` - авторизация
  - [ ] `RetryMiddleware` - retry логика
- [ ] Exception иерархия
  - [ ] `EchoException` (базовое)
  - [ ] `ApiException`
  - [ ] `ValidationException`
  - [ ] `AuthenticationException`
  - [ ] `NetworkException`
  - [ ] `FileException`

**Результат:** Готовый Core слой для работы с API

**Время:** 3-4 дня

---

### Фаза 2: Модели данных
**Задачи:**
- [ ] Enum классы
  - [ ] `TaskType` (transcription, timestamps, diarization, raw)
  - [ ] `Language` (ru, en, de, fr, es, pt, hy, ja, tr, ar, zh, he, vi, auto)
  - [ ] `TranscriptionStatus` (waiting, processing, success, failure)
- [ ] Request модели
  - [ ] `TranscriptionOptions` (task_type, language, censor, etc.)
  - [ ] Использование стандартного `DateTimeInterface` для работы с датами и временем
- [ ] Result модели
  - [ ] `TranscriptPostResult` (file_id)
  - [ ] `TranscriptItemResult` (file_id, task_type, status, result)
  - [ ] `TranscriptBatchResult` (results[], pagination)
  - [ ] `StatusItemResult` (file_id, status, file_size, file_duration)
  - [ ] `StatusBatchResult` (results[], pagination)
  - [ ] `QueueInfoResult` (files_count, files_size, files_duration)
- [ ] Serializer setup
  - [ ] `SerializerInterface`
  - [ ] `SymfonySerializer` - реализация через Symfony Serializer

**Результат:** Типизированные модели для всех API операций

**Время:** 2-3 дня

---

### Фаза 3: Infrastructure - Filesystem
**Задачи:**
- [ ] `FileHelper` - базовые операции с файлами
  - [ ] Проверка существования
  - [ ] Получение размера
  - [ ] Получение MIME типа
  - [ ] Создание временных копий
  - [ ] Удаление временных файлов
  - [ ] Создание директорий
- [ ] `FileValidator` - валидация файлов
  - [ ] Проверка существования и читаемости
  - [ ] Валидация размера (max 500MB)
  - [ ] Валидация MIME типов
  - [ ] Валидация массива файлов
- [ ] `FileUploader` - подготовка для загрузки
  - [ ] Подготовка для multipart/form-data
  - [ ] Cleanup ресурсов

**Результат:** Полноценная работа с файловой системой

**Время:** 1-2 дня

---

### Фаза 4: Services реализация
**Задачи:**
- [ ] `AbstractService` - базовый класс для всех сервисов
- [ ] `TranscriptionService`
  - [ ] `submitTranscription()` - отправка файлов
  - [ ] `getTranscript()` - получение результата
  - [ ] `getTranscriptsByPeriod()` - за период
  - [ ] `getTranscriptsList()` - список по ID
- [ ] `StatusService`
  - [ ] `getFileStatus()` - статус файла
  - [ ] `getUserStatuses()` - статусы пользователя
  - [ ] `getStatusList()` - список статусов
- [ ] `QueueService`
  - [ ] `getQueueInfo()` - информация об очереди

**Результат:** Полная реализация всех API методов

**Время:** 4-5 дней

---

### Фаза 5: Application Layer
**Задачи:**
- [ ] Контракты (интерфейсы)
  - [ ] `TranscriptionServiceInterface`
  - [ ] `StatusServiceInterface`
  - [ ] `QueueServiceInterface`
- [ ] `ServiceFactory` - фабрика сервисов
  - [ ] Инициализация сервисов
  - [ ] Dependency injection
  - [ ] Facade для удобного доступа
- [ ] Batch операции (опционально)
  - [ ] `BatchRequest` - пакетные запросы
  - [ ] `BatchResponse` - пакетные ответы

**Результат:** Удобный API для конечных пользователей

**Время:** 2-3 дня

---

### Фаза 6: Тестирование
**Задачи:**
- [ ] Unit тесты
  - [ ] Тесты моделей (Request, Result, Enum)
  - [ ] Тесты сериализации
  - [ ] Тесты валидации
  - [ ] Тесты FileHelper, FileValidator
  - [ ] Тесты Exception классов
- [ ] Integration тесты
  - [ ] Mock HTTP клиент (`php-http/mock-client`)
  - [ ] Тесты TranscriptionService
  - [ ] Тесты StatusService
  - [ ] Тесты QueueService
  - [ ] Тесты обработки ошибок
  - [ ] Тесты retry логики
- [ ] Fixtures
  - [ ] Примеры аудио файлов
  - [ ] JSON ответы API
  - [ ] Примеры ошибок
- [ ] Custom Assertions
  - [ ] Assertion'ы для API ответов
  - [ ] Assertion'ы для Result объектов
- [ ] Coverage
  - [ ] Достижение 80%+ coverage
  - [ ] HTML отчеты

**Результат:** Полное покрытие тестами, confidence в качестве

**Время:** 5-6 дней

---

### Фаза 7: Документация
**Задачи:**
- [ ] README.md
  - [ ] Описание проекта
  - [ ] Требования
  - [ ] Установка
  - [ ] Quick start
  - [ ] Ссылки на документацию
- [ ] docs/installation.md
  - [ ] Установка через Composer
  - [ ] Настройка credentials
  - [ ] Проверка установки
- [ ] docs/quick-start.md
  - [ ] Первый запрос
  - [ ] Базовые операции
  - [ ] Обработка ошибок
- [ ] docs/api-reference.md
  - [ ] Полное описание всех методов
  - [ ] Параметры и возвращаемые значения
  - [ ] Примеры использования
- [ ] docs/examples/
  - [ ] Базовое использование
  - [ ] Продвинутые функции
  - [ ] Обработка ошибок
  - [ ] Batch операции
  - [ ] Работа с Drive
- [ ] docs/architecture.md
  - [ ] Архитектура SDK
  - [ ] Описание слоев
  - [ ] Диаграммы
- [ ] CONTRIBUTING.md
  - [ ] Как внести вклад
  - [ ] Code style
  - [ ] Pull request process
- [ ] CHANGELOG.md
  - [ ] История изменений
  - [ ] Версионирование

**Результат:** Полная документация для пользователей и контрибьюторов

**Время:** 3-4 дня

---

### Фаза 8: CI/CD и релиз
**Задачи:**
- [ ] GitHub Actions
  - [ ] Workflow для тестов (матрица PHP 8.2, 8.3, 8.4)
  - [ ] Workflow для code quality
  - [ ] Workflow для security checks
  - [ ] Автоматический запуск на PR и push
- [ ] Code quality badges
  - [ ] Tests status
  - [ ] Coverage badge
  - [ ] PHPStan badge
  - [ ] License badge
- [ ] Release процесс
  - [ ] Semantic versioning
  - [ ] Git tags
  - [ ] GitHub releases
  - [ ] Changelog generation
- [ ] Packagist
  - [ ] Регистрация пакета
  - [ ] Auto-update hook
  - [ ] Версионирование

**Результат:** Автоматизированный процесс релизов, опубликованный пакет

**Время:** 2-3 дня

---

## Общая оценка времени

**Всего:** ~22-30 рабочих дней (4-6 недель)

По фазам:
- Фаза 0: Инфраструктура (выполнена)
- Фаза 1: Core (3-4 дня)
- Фаза 2: Модели (2-3 дня)
- Фаза 3: Filesystem (1-2 дня)
- Фаза 4: Services (4-5 дней)
- Фаза 5: Application (2-3 дня)
- Фаза 6: Тестирование (5-6 дней)
- Фаза 7: Документация (3-4 дня)
- Фаза 8: CI/CD (2-3 дня)

## Примеры использования

### Базовое использование

```php
<?php

declare(strict_types=1);

use Rarus\Echo\Application\ServiceFactory;
use Rarus\Echo\Core\Credentials\Credentials;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\Language;

// Создание credentials
$credentials = Credentials::create(
    apiKey: 'your-api-key',
    userId: '00000000-0000-0000-0000-000000000000'
);

// Инициализация приложения
$factory = new ServiceFactory($credentials);

// Получение сервиса транскрибации
$transcription = $app->getTranscriptionService();

// Настройка опций транскрибации
$options = new TranscriptionOptions(
    taskType: TaskType::DIARIZATION,
    language: Language::RU,
    censor: true,
    speakersCorrection: true,
    storeFile: true
);

// Отправка файлов на транскрибацию
$result = $transcription->submitTranscription(
    files: ['/path/to/audio1.mp3', '/path/to/audio2.wav'],
    options: $options
);

// Получение file_id
$fileId = $result->getResults()[0]->getFileId();
echo "File ID: {$fileId}\n";

// Ожидание завершения транскрибации
while (true) {
    $transcript = $transcription->getTranscript($fileId);

    if ($transcript->getStatus() === TranscriptionStatus::SUCCESS) {
        echo "Transcription result:\n";
        echo $transcript->getResult();
        break;
    }

    if ($transcript->getStatus() === TranscriptionStatus::FAILURE) {
        echo "Transcription failed!\n";
        break;
    }

    echo "Waiting... Status: {$transcript->getStatus()->value}\n";
    sleep(5);
}
```

### Проверка статуса

```php
<?php

use Carbon\Carbon;

$status = $app->getStatusService();

// Статус конкретного файла
$fileStatus = $status->getFileStatus($fileId);
echo "Status: {$fileStatus->getStatus()->value}\n";
echo "File size: {$fileStatus->getFileSize()} MB\n";
echo "Duration: {$fileStatus->getFileDuration()} min\n";

// Статусы всех файлов пользователя за период
$startDate = Carbon::parse('2025-01-01')->startOfDay();
$endDate = Carbon::parse('2025-12-31')->endOfDay();

$userStatuses = $status->getUserStatuses($startDate, $endDate, page: 1, perPage: 50);
foreach ($userStatuses->getResults() as $item) {
    echo "File: {$item->getFileId()}, Status: {$item->getStatus()->value}\n";
}
```

### Проверка очереди

```php
<?php

$queue = $app->getQueueService();
$queueInfo = $queue->getQueueInfo();

echo "Files in queue: {$queueInfo->getFilesCount()}\n";
echo "Total size: {$queueInfo->getFilesSize()} MB\n";
echo "Total duration: {$queueInfo->getFilesDuration()} min\n";
```

### Обработка ошибок

```php
<?php

use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\FileException;

try {
    $result = $transcription->submitTranscription(
        files: ['/path/to/audio.mp3'],
        options: $options
    );
} catch (FileException $e) {
    echo "File error: {$e->getMessage()}\n";
    // Файл не найден, слишком большой, неверный MIME тип
} catch (ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
    // Неверные параметры запроса
    // $e->getValidationErrors() - детали валидации
} catch (AuthenticationException $e) {
    echo "Authentication error: {$e->getMessage()}\n";
    // Неверный API ключ
} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
    // Ошибка API (400, 500)
} catch (\Exception $e) {
    echo "Unexpected error: {$e->getMessage()}\n";
}
```

## Дополнительные возможности

### Планируемые в будущем

1. **Async support** - интеграция с ReactPHP/Amp для асинхронных запросов
2. **Webhook handler** - обработка webhook'ов от сервиса
3. **File streaming** - поддержка стриминга больших файлов
4. **Cache layer** - PSR-6/PSR-16 кеширование результатов
5. **Rate limiting** - контроль rate limits
6. **Batch operations с Generator** - эффективная обработка больших объемов данных
7. **CLI утилита** - консольное приложение для работы с API
8. **Laravel integration** - Service Provider для Laravel
9. **Symfony bundle** - Bundle для Symfony

## Лицензия

MIT License

## Ссылки

- **API документация:** https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json
- **Bitrix24 PHP SDK (reference):** https://github.com/bitrix24/b24phpsdk
- **PSR стандарты:** https://www.php-fig.org/psr/
- **Symfony компоненты:** https://symfony.com/components
- **HTTPlug:** http://docs.php-http.org/
