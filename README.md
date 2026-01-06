# RARUS Echo PHP SDK

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

PHP SDK для сервиса транскрибации RARUS Echo с использованием стандартов PSR и компонентов Symfony.

## Статус проекта

✅ **beta** - SDK покрывает текущую версию API.

## Возможности

- ✅ Асинхронная транскрибация аудио и видео файлов
- ✅ Поддержка 13 языков (включая автоопределение)
- ✅ Различные типы транскрибации (обычная, с метками времени, с диаризацией)
- ✅ Управление очередью транскрибации
- ✅ Интеграция с Rarus Drive
- ✅ PSR-совместимость (PSR-3, PSR-7, PSR-17, PSR-18)
- ✅ Использование Symfony компонентов
- ✅ Автоматическое обнаружение HTTP клиента (php-http/discovery)

## Требования

- PHP 8.2, 8.3 или 8.4
- Composer 2.x
- Расширения: json, curl, mbstring, fileinfo

## Установка

```bash
composer require rarus/echo-php-sdk
```

## Разработка с Claude Code

Этот проект настроен для работы с [Claude Code](https://claude.com/claude-code).

### Автоматическое тестирование
При работе с Claude Code юнит-тесты **автоматически запускаются** после завершения задачи. Это настроено через `.claude/settings.json` в репозитории.

**Как это работает:**
- Claude изменяет код (инструменты Write или Edit)
- Тесты запускаются автоматически через `make test-unit`
- Результаты тестов показываются сразу
- Claude видит ошибки и может их исправить

**Отключить временно** (если нужно):
Создайте `.claude/settings.local.json`:
```json
{
  "hooks": {
    "PostToolUse": []
  }
}
```

Это переопределит настройки проекта локально, не затрагивая репозиторий.

## Быстрый старт

### Базовое использование

```php
<?php

declare(strict_types=1);

use Rarus\Echo\Application\ServiceFactory;use Rarus\Echo\Core\Credentials;use Rarus\Echo\Enum\Language;use Rarus\Echo\Enum\TaskType;use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

// Создание credentials
$credentials = Credentials::fromString(
    apiKey: 'your-api-key-as-uuid',
    userId: '00000000-0000-0000-0000-000000000000'
);

// Или из переменных окружения
// $factory = ServiceFactory::fromEnvironment();

// Инициализация приложения
$factory = new ServiceFactory($credentials);

// Настройка опций транскрибации
$options = TranscriptionOptions::create()
    ->withTaskType(TaskType::DIARIZATION)  // С разбиением по говорящим
    ->withLanguage(Language::RU)            // Русский язык
    ->withCensor(true)                      // С цензурой
    ->build();

// Отправка файлов на транскрибацию
$result = $factory->getTranscriptionService()->submitTranscription(
    files: ['/path/to/audio.mp3', '/path/to/audio2.wav'],
    options: $options
);

$fileId = $result->getFirstFileId();
echo "Файл отправлен: {$fileId}\n";

// Проверка статуса
$status = $factory->getStatusService()->getFileStatus($fileId);
echo "Статус: {$status->getStatus()->value}\n";

// Ожидание завершения и получение результата
while (!$status->isCompleted()) {
    sleep(5);
    $transcript = $factory->getTranscriptionService()->getTranscript($fileId);

    if ($transcript->isSuccessful()) {
        echo "Результат:\n{$transcript->getResult()}\n";
        break;
    }
}
```

### С обработкой ошибок

```php
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\ApiException;

try {
    $result = $factory->getTranscriptionService()->submitTranscription($files, $options);
} catch (FileException $e) {
    // Ошибка файла (не найден, не читается, неверный формат)
    echo "Ошибка файла: {$e->getMessage()}\n";
} catch (ValidationException $e) {
    // Ошибка валидации (422)
    echo "Ошибка валидации: {$e->getMessage()}\n";
    echo "Детали:\n{$e->getValidationErrorsAsString()}\n";
} catch (AuthenticationException $e) {
    // Ошибка аутентификации (401)
    echo "Ошибка аутентификации: {$e->getMessage()}\n";
} catch (ApiException $e) {
    // Общая ошибка API
    echo "Ошибка API: {$e->getMessage()}\n";
}
```

Полные примеры использования:
- [examples/basic-usage.php](examples/basic-usage.php) - базовый функционал
- [examples/advanced-usage.php](examples/advanced-usage.php) - пакетная обработка, мониторинг, статистика

## Документация
- 📚 [OpenAPI спецификация](https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json) - официальная API документация

## Разработка

### Требования для разработки

- Docker & Docker Compose
- Make

### Первоначальная настройка

```bash
# Инициализация Docker окружения
make docker-init

# Установка зависимостей
make composer-install
```

### Работа с кодом

```bash
# Запуск всех линтеров
make lint-all

# Исправление стиля кода
make lint-cs-fixer-fix

# Статический анализ
make lint-phpstan

# Запуск тестов
make test-unit
make test-integration
make test-all

# Генерация coverage
make test-coverage
```

### Доступные Make команды

Полный список команд:
```bash
make help
```

Основные команды:
- `make docker-init` - первоначальная настройка
- `make docker-up` - запуск контейнеров
- `make composer-install` - установка зависимостей
- `make lint-all` - запуск всех линтеров
- `make test-all` - запуск всех тестов
- `make ci` - полный CI pipeline локально
- `make php-cli-bash` - войти в контейнер

## Архитектура

SDK использует многослойную архитектуру:

```
Application Layer (ServiceFactory)
    ↓
Services Layer (Transcription, Status, Queue)
    ↓
Core Layer (ApiClient, Credentials)
    ↓
Infrastructure Layer (HttpClient, Serializer, Filesystem)
```

### Основные компоненты

- **Application** - точка входа, контракты сервисов
- **Services** - бизнес-логика для работы с API
- **Core** - базовый API клиент и credentials
- **Infrastructure** - HTTP клиент, сериализация, работа с файлами
- **Enum** - типизированные перечисления
- **Exception** - иерархия исключений

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

## Используемые технологии

### PSR стандарты
- PSR-3: Logger Interface
- PSR-4: Autoloading
- PSR-7: HTTP Message Interface
- PSR-12: Extended Coding Style
- PSR-17: HTTP Factories
- PSR-18: HTTP Client

### Symfony компоненты
- symfony/http-client
- symfony/serializer
- symfony/filesystem
- symfony/validator
- symfony/mime

### HTTP абстракция
- php-http/discovery
- php-http/httplug
- php-http/message

### Инструменты качества
- PHPStan (level 8)
- PHP CS Fixer (PSR-12)
- Rector
- PHPUnit

## Вклад в проект

Мы приветствуем вклад в развитие проекта! Пожалуйста, ознакомьтесь с [CONTRIBUTING.md](CONTRIBUTING.md).

### Процесс разработки

1. Fork репозитория
2. Создайте feature branch
3. Внесите изменения
4. Запустите тесты и линтеры: `make ci`
5. Создайте Pull Request

## Лицензия

MIT License. См. [LICENSE](LICENSE) для деталей.

## Поддержка

Если у вас возникли вопросы или проблемы, пожалуйста, создайте [Issue](../../issues).