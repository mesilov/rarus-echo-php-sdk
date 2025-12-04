# RARUS Echo PHP SDK

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

PHP SDK для сервиса транскрибации RARUS Echo с использованием стандартов PSR и компонентов Symfony.

## Статус проекта

🚧 **В разработке** - SDK находится в активной разработке согласно [плану реализации](PLAN.md)

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

- PHP 8.1, 8.2 или 8.3
- Composer 2.x
- Расширения: json, curl, mbstring, fileinfo

## Установка

```bash
composer require rarus/echo-php-sdk
```

## Быстрый старт

```php
<?php

declare(strict_types=1);

use Rarus\Echo\Application\EchoApplication;
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
$app = new EchoApplication($credentials);

// Отправка файла на транскрибацию
$options = new TranscriptionOptions(
    taskType: TaskType::TRANSCRIPTION,
    language: Language::RU
);

$result = $app->getTranscriptionService()->submitTranscription(
    files: ['/path/to/audio.mp3'],
    options: $options
);

$fileId = $result->getResults()[0]->getFileId();

// Получение результата
$transcript = $app->getTranscriptionService()->getTranscript($fileId);
echo $transcript->getResult();
```

## Документация

- 📋 [План реализации](PLAN.md) - детальный план разработки SDK
- 📚 [API Reference](docs/api-reference.md) - описание всех методов (в разработке)
- 🚀 [Quick Start](docs/quick-start.md) - руководство по началу работы (в разработке)
- 🏗️ [Архитектура](docs/architecture.md) - архитектура SDK (в разработке)
- 💡 [Примеры](docs/examples/) - примеры использования (в разработке)

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
Application Layer (EchoApplication)
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

Мы приветствуем вклад в развитие проекта! Пожалуйста, ознакомьтесь с [CONTRIBUTING.md](CONTRIBUTING.md) (в разработке).

### Процесс разработки

1. Fork репозитория
2. Создайте feature branch
3. Внесите изменения
4. Запустите тесты и линтеры: `make ci`
5. Создайте Pull Request

## Лицензия

MIT License. См. [LICENSE](LICENSE) для деталей.

## Ссылки

- [API документация](https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json)
- [План реализации](PLAN.md)
- [Bitrix24 PHP SDK](https://github.com/bitrix24/b24phpsdk) (reference architecture)

## Поддержка

Если у вас возникли вопросы или проблемы, пожалуйста, создайте [Issue](../../issues).

---

**Примечание:** Этот SDK находится в активной разработке. API может изменяться до релиза версии 1.0.0.
