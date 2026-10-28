# Установка RARUS Echo

[Документация](../README.md) · [Главная](../../README.md)

Готовые пакеты CLI включают PHP runtime, FFmpeg/ffprobe и лицензии. PHP, Composer и Docker для standalone не нужны. Короткие команды скачивания через `curl` из Latest находятся в [README](../../README.md#установка).

## Выбор способа

- [Установщики и ручная установка](#standalone-без-php-composer-и-docker) — готовые бинарные пакеты.
- [Docker](docker.md) — запуск готового образа.
- [PHP SDK и Composer CLI](php.md) — интеграция в PHP-приложение.
- [Сборка из исходников](../../build/README.md) — для разработчиков standalone.

## Доступность релизов

На 28.10.2026 Latest stable — `0.4.0`, без standalone-архивов. README заранее содержит постоянные Latest URL; до stable с бинарями используйте явный `0.5.0-rc-3`, как показано ниже. RC не заменяет Latest. Проверка stable-ссылок зависит от [release-задачи #68](https://github.com/mesilov/rarus-echo-php-sdk/issues/68).

## Standalone: без PHP, Composer и Docker

## Установщики

Для проверки prerelease `0.5.0-rc-3` используйте явную версию. Linux x86-64/ARM64 и macOS Apple Silicon:

```bash
curl -fsSL --proto '=https' --proto-redir '=https' https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.sh | sh -s -- --version 0.5.0-rc-3
```

Windows x86-64, PowerShell 5.1 или новее:

```powershell
& ([scriptblock]::Create((Invoke-WebRequest -UseBasicParsing https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.ps1).Content)) -Version 0.5.0-rc-3
```

`0.5.0-rc-3` — release candidate: бинарники собираются из отмеченного tag коммита `main` и публикуются в [GitHub prerelease](https://github.com/mesilov/rarus-echo-php-sdk/releases/tag/0.5.0-rc-3). Этот prerelease не меняет Latest stable. Без параметра версии установщик выбирает последний стабильный релиз; текущий stable `0.4.0` не содержит standalone-архивов, поэтому для RC параметр версии обязателен. Если нужный архив отсутствует, установщик завершится с ошибкой.

Скрипт определяет платформу, проверяет SHA-256 и версию выбранного бинарника, затем устанавливает его в пользовательский каталог. PHP, Composer, Docker и права администратора не нужны. На Unix нужны `curl`, `tar`, стандартные shell-утилиты и `sha256sum` (Linux) / `shasum` (macOS). Неподдерживаемые платформы, включая Intel macOS и Windows ARM64, отклоняются до скачивания.

| Параметр | POSIX sh | PowerShell |
| --- | --- | --- |
| Конкретный tag, включая prerelease | `--version TAG` | `-Version TAG` |
| Абсолютный каталог бинарника | `--install-dir PATH` | `-InstallDir PATH` |
| Не менять постоянный PATH | `--no-modify-path` | `-NoModifyPath` |

Параметры передаются скрипту так (замените `TAG` существующим tag с архивами):

```bash
curl -fsSL --proto '=https' --proto-redir '=https' https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.sh | sh -s -- --version TAG --install-dir "$HOME/.local/bin" --no-modify-path
```

```powershell
& ([scriptblock]::Create((Invoke-WebRequest -UseBasicParsing https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.ps1).Content)) -Version TAG -InstallDir "$env:LOCALAPPDATA\rarus-echo\bin" -NoModifyPath
```

На Unix бинарник помещается в `$HOME/.local/bin`, сведения о сборке и лицензии — в `$HOME/.local/share/rarus-echo/package`. Каталог `package` целиком принадлежит установщику и заменяется при обновлении; храните свои файлы вне него. Если целевой бинарник или файл активации уже является symlink, установка завершается с ошибкой, сохраняя ссылку. Для будущих bash/zsh-сессий скрипт добавляет строку активации в `.bashrc`, выбранный bash login profile (`.bash_profile`, `.bash_login` или `.profile`) и `${ZDOTDIR:-$HOME}/.zshrc`. Для текущей сессии выполните:

```bash
. "$HOME/.local/share/rarus-echo/env"
rarus-echo --version
```

На Windows бинарник по умолчанию находится в `%LOCALAPPDATA%\rarus-echo\bin`, лицензии — в `%LOCALAPPDATA%\rarus-echo\package`; с `-InstallDir` лицензии сохраняются в подкаталоге `package` выбранного каталога. Установщик обновляет пользовательский PATH и печатает команду активации для текущей сессии; уже открытые терминалы могут требовать перезапуска. Повторный запуск обновляет бинарник и лицензии; конфигурация подключения сохраняется. Установка бинарника и команда `rarus-echo install` для настройки подключения — отдельные шаги.

## Ручная установка

В [GitHub Releases](https://github.com/mesilov/rarus-echo-php-sdk/releases) скачайте архив своей платформы и `SHA256SUMS` из одного релиза. В prerelease `0.5.0-rc-3` опубликованы все четыре архива и `SHA256SUMS`; публичная установка проверена на четырёх платформах. Latest stable `0.4.0` их не содержит; до выхода нового stable выбирайте явный RC tag.

| Платформа | Архив | Исполняемый файл |
| --- | --- | --- |
| Linux x86-64 | `rarus-echo-linux-amd64.tar.gz` | `rarus-echo` |
| Linux ARM64 | `rarus-echo-linux-arm64.tar.gz` | `rarus-echo` |
| macOS Apple Silicon | `rarus-echo-macos-arm64.tar.gz` | `rarus-echo` |
| Windows x86-64 | `rarus-echo-windows-amd64.zip` | `rarus-echo.exe` |

Linux, проверка скачанного архива и запуск:

```bash
sha256sum --check --ignore-missing SHA256SUMS
tar -xzf rarus-echo-linux-amd64.tar.gz
./rarus-echo --version
./rarus-echo --help
```

На macOS проверьте строку своего архива командой `grep '  rarus-echo-macos-arm64.tar.gz$' SHA256SUMS | shasum -a 256 -c`, затем распакуйте его: `tar -xzf rarus-echo-macos-arm64.tar.gz`.

Windows PowerShell:

```powershell
$archive = 'rarus-echo-windows-amd64.zip'
$line = (Select-String -Path .\SHA256SUMS -Pattern ('  ' + [regex]::Escape($archive) + '$')).Line
$expected = ($line -split '  ')[0]
if (!$expected -or (Get-FileHash $archive -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expected) {
    throw 'SHA256 mismatch'
}
Expand-Archive $archive -DestinationPath .\rarus-echo
.\rarus-echo\rarus-echo.exe --version
.\rarus-echo\rarus-echo.exe --help
```

`--version` standalone-сборки выводит версию и полный source SHA; они совпадают с `build-info.json` рядом с файлом. Для подготовки видео сохраняйте весь распакованный комплект: `libexec/ffmpeg`, `libexec/ffprobe` (на Windows с `.exe`), media metadata, контрольные суммы, лицензии и сжатые соответствующие исходники. Дополнительная установка FFmpeg не требуется. Опубликованный до этого изменения `0.5.0-rc-1` остаётся доступен по точной версии с прежним CLI без медианабора. Справка, версия и команды без обработки видео продолжают работать с одним исполняемым файлом. Windows использует временный каталог для встроенного PHAR и очищает его при завершении.

Новые сборки сжимают CLI через UPX (Linux/Windows) или wrappe (macOS); отчёт — `compression-info.json`. На macOS нужен доступный для записи и исполнения временный каталог: распакованный бинарь сохраняется в `rarus-echo-cache` для следующих запусков. Состав и проверка сборки описаны в [build/README.md](../../build/README.md).

macOS-сборка имеет ad-hoc подпись без notarization, Windows-сборка не подписана Authenticode; система может запросить подтверждение запуска скачанного файла. Альтернативный запуск описан в [руководстве по Docker](docker.md).

## Каталог пакета и PATH при ручной установке

Скачивайте архив и `SHA256SUMS` в отдельный пустой каталог. Проверяйте checksum до распаковки. Если за время двух Latest-загрузок вышел новый релиз и проверка не проходит, скачайте оба файла заново из одного конкретного tag. При любой ошибке загрузки или проверки не распаковывайте и не запускайте пакет.

Архив распаковывается без внешнего каталога-обёртки: создайте каталог `rarus-echo` и распакуйте весь архив туда. Оставляйте `build-info.json`, `libexec/`, media metadata, исходники и лицензии рядом с исполняемым файлом. Можно работать из этого каталога через `./rarus-echo` (PowerShell: `.\rarus-echo.exe`). Для вызова из любой директории добавьте полный путь к каталогу пакета в PATH. Например, для текущей Bash/zsh-сессии, находясь в каталоге пакета:

```sh
export PATH="$PWD:$PATH"
rarus-echo --version
```

PowerShell, из каталога пакета:

```powershell
$env:Path = "$($PWD.Path);$env:Path"
rarus-echo.exe --version
```

Для постоянного PATH используйте настройки своей оболочки/ОС либо установщик выше. Не переносите один бинарник без остальных файлов пакета.

## Обновление

При кратковременной блокировке `.exe` Windows-установщик повторяет атомарную замену до 20 раз с паузой 250 мс. Ошибки доступа и прочие ошибки не повторяются. При постоянной блокировке закройте запущенные CLI-процессы и повторите установку; прежняя установка сохраняется через штатный rollback.

Повторный запуск установщика обновляет его собственные файлы и сохраняет конфиг. При ручном обновлении скачайте и проверьте новый архив в отдельном каталоге, выполните `--version`, затем переключите PATH на новый полный пакет. Не распаковывайте новый архив поверх старого пакета: так могут остаться файлы прежней версии. Конфиг подключения находится в пользовательском каталоге и не зависит от бинарника; [порядок чтения и явный путь](../connection.md).

## Диагностика первого запуска

- HTTP 404 у Latest: проверьте наличие архивов в стабильном релизе; до публикации используйте явный RC.
- Команда не найдена: активируйте PATH установщика или запустите бинарник из распакованного каталога.
- Нет media toolkit или он повреждён: восстановите весь комплект из проверенного архива. Произвольный FFmpeg в PATH не заменяет встроенный набор.
- Нет подключения: выполните `rarus-echo install`, затем `rarus-echo doctor`; [конфигурация](../connection.md) и [отчёт doctor](../cli.md#диагностика-окружения).
- Intel macOS и Windows ARM64 не поддерживаются; выберите поддерживаемую машину или [Docker](docker.md).
