# CLI через Docker

[Установка](README.md) · [Документация](../README.md)

## Запуск CLI

Для запуска CLI из готового образа требуется Docker; PHP-пакет на хосте устанавливать не нужно. Docker использует локально закешированный tag, если он уже загружен; добавьте `--pull=always` к `docker run`, если нужно принудительно получить актуальный опубликованный image.

```bash
docker run --rm ghcr.io/mesilov/rarus-echo-php-sdk:cli
```

Image использует `rarus-echo` как entrypoint, поэтому команды передаются сразу после имени image:

```bash
docker run --rm \
  -e RARUS_ECHO_API_KEY=your-api-key-uuid \
  -e RARUS_ECHO_USER_ID=your-user-id-uuid \
  ghcr.io/mesilov/rarus-echo-php-sdk:cli queue --json

docker run --rm \
  -e RARUS_ECHO_API_KEY=your-api-key-uuid \
  -e RARUS_ECHO_USER_ID=your-user-id-uuid \
  -v "$PWD/audio.ogg:/audio.ogg:ro" \
  ghcr.io/mesilov/rarus-echo-php-sdk:cli submit /audio.ogg \
    --task-type=diarization \
    --language=ru \
    --speakers-correction \
    --timestamps-extended \
    --wait \
    --json

docker run --rm \
  --env-file .env.local \
  -v "$PWD/audio.ogg:/audio.ogg:ro" \
  ghcr.io/mesilov/rarus-echo-php-sdk:cli submit /audio.ogg \
    --language=ru \
    --wait \
    --raw-result > transcript.txt
```

GitHub Actions собирает image для `linux/amd64` и `linux/arm64`, проверяет сборку в pull request и публикует `ghcr.io/mesilov/rarus-echo-php-sdk:cli` при изменениях в `dev`, `main` или ручном запуске workflow.

Image собирается на официальной runtime-базе `php:8.4-cli-alpine`. По сравнению с прежней базой `php:8.4-cli-bookworm` это уменьшает опубликованный image примерно с `769MB` до `~179MB` по `docker images` и примерно со `~180MB` до `~46MB` по сжатому pull size. Поведение `rarus-echo`, расширения `curl`/`fileinfo`/`mbstring`, PHP-лимиты для локальных аудио-smoke и multi-arch публикация сохранены.

Подключение через конфиг и `--config` описано в [руководстве](../connection.md). Для видео см. [подготовку аудио](../cli.md#локальное-видео).
