# PHP SDK: примеры и API

[Документация](README.md) · [Главная](../README.md)

[Требования и установка через Composer](install/php.md). Примеры выполняются после подключения `vendor/autoload.php`.



## Быстрый старт

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
    ->withSpeakersCorrection()
    ->withTimestampsExtended()
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

Для диаризации с расширенными таймкодами используйте `task-type=diarization` вместе с опцией `timestamps-extended=1`: в SDK это `withTimestampsExtended()`, в CLI - `--timestamps-extended`.

### Языки
`ru`, `en`, `de`, `fr`, `es`, `pt`, `hy`, `ja`, `tr`, `ar`, `zh`, `he`, `vi`, `auto`

### Статусы
- `waiting` - ожидает в очереди
- `processing` - обрабатывается
- `success` - завершено успешно
- `failure` - ошибка
