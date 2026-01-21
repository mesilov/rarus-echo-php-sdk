# RARUS Echo PHP SDK

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

PHP SDK для сервиса транскрибации RARUS Echo с использованием стандартов PSR и компонентов Symfony.

## Статус проекта

**beta** - SDK покрывает текущую версию API.

## Возможности

- Асинхронная транскрибация аудио и видео файлов
- Поддержка 13 языков (включая автоопределение)
- Различные типы транскрибации (обычная, с метками времени, с диаризацией)
- Управление очередью транскрибации
- Интеграция с Rarus Drive
- PSR-совместимость (PSR-3, PSR-7, PSR-17, PSR-18)
- Автоматическое обнаружение HTTP клиента (php-http/discovery)

## Требования

- PHP 8.2, 8.3 или 8.4
- Composer 2.x
- Расширения: json, curl, mbstring, fileinfo

## Установка

```bash
composer require rarus/echo-php-sdk
```

## Быстрый старт

### Базовое использование

```php
<?php

declare(strict_types=1);

use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Core\Credentials;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Symfony\Component\Uid\Uuid;

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
echo "Файл отправлен: {$fileId}\n";

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

### Первоначальная настройка

```bash
make docker-init      # Инициализация Docker окружения и установка зависимостей
make docker-up        # Запуск контейнеров
make php-cli-bash     # Войти в контейнер
```

### Основные команды

```bash
make lint-all         # Запуск всех линтеров
make lint-cs-fixer-fix # Исправление стиля кода
make lint-phpstan     # Статический анализ
make test-unit        # Юнит-тесты
make test-integration # Интеграционные тесты
make test-all         # Все тесты
make ci               # Полный CI pipeline локально
```

Полный список команд: `make help`

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
