<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\BuildVersion;

final class BuildVersionTest extends TestCase
{
    public function testReportsEmbeddedVersionAndFullSourceSha(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'echo-build-');
        self::assertIsString($path);
        $sha = str_repeat('a', 40);
        file_put_contents($path, json_encode(['version' => 'v0.5.0', 'source_sha' => $sha], JSON_THROW_ON_ERROR));

        try {
            self::assertSame('v0.5.0 (source ' . $sha . ')', BuildVersion::get($path));
        } finally {
            unlink($path);
        }
    }

    public function testFallsBackForComposerInstallWithoutBuildMetadata(): void
    {
        self::assertNotSame('UNKNOWN', BuildVersion::get('/missing/echo-build-info.json'));
    }

    public function testRejectsInvalidBuildIdentity(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'echo-build-');
        self::assertIsString($path);
        file_put_contents($path, '{"version":"0.5.0","source_sha":"incorrect"}');

        try {
            $this->expectException(\RuntimeException::class);
            BuildVersion::get($path);
        } finally {
            unlink($path);
        }
    }
}
