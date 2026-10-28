# RARUS Echo PHP SDK

Транскрибируйте аудио и видео через [RARUS Echo](https://rarus.ru/echo/?utm_source=mesilov_maksim&utm_medium=github&utm_campaign=rarus_echo_php_sdk) из терминала, PHP-приложений и AI-агентов.
Готовый CLI включает PHP runtime и инструменты подготовки аудио из видео.

[![Lint](https://github.com/mesilov/rarus-echo-php-sdk/actions/workflows/lint.yml/badge.svg)](https://github.com/mesilov/rarus-echo-php-sdk/actions/workflows/lint.yml)
[![Tests](https://github.com/mesilov/rarus-echo-php-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/mesilov/rarus-echo-php-sdk/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

SDK в статусе **beta** покрывает текущую версию API: асинхронная транскрибация,
диаризация, таймкоды, 13 языков и автоопределение. PHP SDK поддерживает PSR-3/7/17/18
и автоматическое обнаружение HTTP-клиента.

## Скачать

Готовые пакеты — в [Latest Release](https://github.com/mesilov/rarus-echo-php-sdk/releases/latest).
Прямые ссылки всегда указывают на последний стабильный релиз.

**На 28.10.2026 Latest ещё не содержит бинарных пакетов.** Ссылки и команды ниже
подготовлены для stable с бинарями; до его публикации используйте
[инструкции для доступного prerelease](docs/install/README.md#установщики).

| Платформа | Архитектура | Скачать |
| --- | --- | --- |
| Linux | amd64 | [rarus-echo-linux-amd64.tar.gz](https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/rarus-echo-linux-amd64.tar.gz) |
| Linux | arm64 | [rarus-echo-linux-arm64.tar.gz](https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/rarus-echo-linux-arm64.tar.gz) |
| macOS | arm64 (Apple Silicon) | [rarus-echo-macos-arm64.tar.gz](https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/rarus-echo-macos-arm64.tar.gz) |
| Windows | amd64 | [rarus-echo-windows-amd64.zip](https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/rarus-echo-windows-amd64.zip) |

## Установка

Скачайте пакет через `curl`, проверьте SHA-256 и распакуйте **весь комплект**.
PHP, Composer и Docker устанавливать не нужно. Выполняйте команды в новом пустом каталоге.

### Linux amd64 / arm64

Для ARM64 замените имя архива на `rarus-echo-linux-arm64.tar.gz`.

```sh
archive=rarus-echo-linux-amd64.tar.gz
curl -fL -o "$archive" "https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/$archive" &&
curl -fL -o SHA256SUMS https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/SHA256SUMS &&
sha256sum --check --ignore-missing SHA256SUMS &&
mkdir rarus-echo && tar -xzf "$archive" -C rarus-echo &&
./rarus-echo/rarus-echo --version
```

### macOS arm64

```sh
archive=rarus-echo-macos-arm64.tar.gz
curl -fL -o "$archive" "https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/$archive" &&
curl -fL -o SHA256SUMS https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/SHA256SUMS &&
grep '  rarus-echo-macos-arm64.tar.gz$' SHA256SUMS | shasum -a 256 -c &&
mkdir rarus-echo && tar -xzf "$archive" -C rarus-echo &&
./rarus-echo/rarus-echo --version
```

<details>
<summary>Windows amd64 — PowerShell</summary>

```powershell
$ErrorActionPreference = 'Stop'
$archive = 'rarus-echo-windows-amd64.zip'
curl.exe -fL -o $archive "https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/$archive"
if ($LASTEXITCODE -ne 0) { throw 'Archive download failed' }
curl.exe -fL -o SHA256SUMS https://github.com/mesilov/rarus-echo-php-sdk/releases/latest/download/SHA256SUMS
if ($LASTEXITCODE -ne 0) { throw 'Checksum download failed' }
$record = @(Get-Content SHA256SUMS | Where-Object { $_ -match '^[a-fA-F0-9]{64}  rarus-echo-windows-amd64\.zip$' })
if ($record.Count -ne 1) { throw 'Missing or duplicate checksum' }
if ((Get-FileHash $archive -Algorithm SHA256).Hash -ne ($record[0] -split '  ')[0]) { throw 'Checksum mismatch' }
New-Item -ItemType Directory -Path rarus-echo | Out-Null
Expand-Archive -LiteralPath $archive -DestinationPath rarus-echo
.\rarus-echo\rarus-echo.exe --version
```

</details>

Обновление, PATH, ограничения платформ и диагностика запуска — в
[руководстве по установке](docs/install/README.md).
Сохраняйте `libexec/`, metadata и лицензии рядом с бинарником: они нужны для работы полного пакета.

## Первый запуск

Нужны API key и User ID сервиса Echo. Из каталога распакованного пакета:

```sh
cd rarus-echo
./rarus-echo install
./rarus-echo doctor
./rarus-echo queue --json
./rarus-echo submit /path/to/audio.ogg --language=ru --wait --output=transcript.txt
```

В PowerShell используйте `.\rarus-echo.exe` вместо `./rarus-echo` и свой путь к аудио.
`install` сохраняет подключение, API key вводится скрыто. `submit` также принимает
локальное видео и сам подготавливает аудиодорожку.
Для сохранения аудио без транскрибации и подключения: `rarus-echo extract-audio video.mp4`.
[Конфигурация подключения](docs/connection.md) · [Команды и опции CLI](docs/cli.md).

## Команды CLI

| Команда | Назначение |
| --- | --- |
| `rarus-echo install` | Настроить подключение и сохранить приватный конфиг. |
| `rarus-echo doctor` | Проверить конфиг, доступ к Echo и готовность медианабора. |
| `rarus-echo extract-audio <file>` | Сохранить аудио рядом с видео с тем же базовым именем, без отправки в Echo. |
| `rarus-echo queue` | Показать сводную информацию об очереди транскрибации. |
| `rarus-echo submit <files>...` | Отправить один или несколько аудио- или видеофайлов на транскрибацию. |
| `rarus-echo status <file-id>` | Показать статус транскрибации файла по UUID. |
| `rarus-echo transcript <file-id>` | Получить результат транскрибации файла по UUID. |
| `rarus-echo list` | Показать список публичных команд. |
| `rarus-echo help <command>` | Показать справку выбранной команды, например `rarus-echo help submit`. |
| `rarus-echo completion` | Вывести скрипт автодополнения для оболочки. |

Скрытая служебная команда `rarus-echo _complete` выдаёт подсказки скрипту автодополнения;
обычно её вызывает оболочка автоматически.
Аргументы, опции и примеры — в [полном справочнике CLI](docs/cli.md).

## Документация

- [Установка и обновление](docs/install/README.md).
- [Подключение и конфигурация](docs/connection.md).
- [CLI: команды, опции и обработка видео](docs/cli.md).
- [PHP SDK: установка](docs/install/php.md), [примеры и API](docs/php-sdk.md).
- [Agent plugin для транскрибации](docs/agent-plugin.md).
- [Разработка и сопровождение](docs/development.md), [CONTRIBUTING](CONTRIBUTING.md).
- [Вся документация](docs/README.md), [OpenAPI](https://production-ai-ui-api.ai.rarus-cloud.ru/openapi.json).

Вопросы и ошибки: [GitHub Issues](https://github.com/mesilov/rarus-echo-php-sdk/issues).
Лицензия: [MIT](LICENSE).
