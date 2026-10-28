# PHP SDK и Composer CLI

[Установка](README.md) · [Документация](../README.md)

## Требования

- PHP 8.4 или 8.5
- Composer 2.x
- Расширения: json, curl, mbstring, fileinfo

## Установка

```bash
composer require mesilov/rarus-echo-php-sdk:0.5.0-rc-2
```

Этот пример устанавливает проверяемый RC; stable пока `0.4.0`. При выпуске stable обновляйте constraint здесь.

После установки доступен CLI:

```sh
vendor/bin/rarus-echo --help
```

[Примеры PHP SDK](../php-sdk.md) · [Подключение CLI](../connection.md) · [Справочник CLI](../cli.md)

Для обработки видео через Composer CLI задайте совместимую пару `RARUS_ECHO_FFMPEG_PATH` / `RARUS_ECHO_FFPROBE_PATH`; подробности — в [руководстве CLI](../cli.md#локальное-видео). PHP SDK и обычное аудио FFmpeg не требуют.
