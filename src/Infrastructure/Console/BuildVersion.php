<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

use Composer\InstalledVersions;

/** @internal Build identity embedded by the standalone packager. */
final class BuildVersion
{
    public static function get(?string $metadataPath = null): string
    {
        $metadataPath ??= dirname(__DIR__, 3) . '/build-info.json';
        if (!is_file($metadataPath)) {
            return InstalledVersions::getPrettyVersion('mesilov/rarus-echo-php-sdk') ?? 'dev';
        }

        $contents = file_get_contents($metadataPath);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read CLI build metadata.');
        }

        $metadata = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($metadata)
            || !is_string($metadata['version'] ?? null)
            || $metadata['version'] === ''
            || !is_string($metadata['source_sha'] ?? null)
            || preg_match('/^[0-9a-f]{40}$/D', $metadata['source_sha']) !== 1
        ) {
            throw new \RuntimeException('Invalid CLI build identity.');
        }

        return $metadata['version'] . ' (source ' . $metadata['source_sha'] . ')';
    }
}
