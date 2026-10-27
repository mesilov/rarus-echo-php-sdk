<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Configuration;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;

final class ConfigPathResolverTest extends TestCase
{
    public function testUnixDefaultsAndRelativeOverride(): void
    {
        $paths = new ConfigPathResolver('/work', ['HOME' => '/user', 'XDG_CONFIG_HOME' => '/settings'], 'Linux');
        self::assertSame('/settings/rarus-echo/config.json', $paths->resolve());
        self::assertSame('/work/личный конфиг.json', $paths->resolve('личный конфиг.json'));
        self::assertSame('/elsewhere/file.json', $paths->resolve('/elsewhere/file.json'));
    }

    public function testRelativeXdgFallsBackToHome(): void
    {
        $paths = new ConfigPathResolver('/work', ['HOME' => '/user', 'XDG_CONFIG_HOME' => 'relative'], 'Darwin');
        self::assertSame('/user/.config/rarus-echo/config.json', $paths->resolve());
    }

    public function testWindowsUsesAppDataAndAcceptsDrivePaths(): void
    {
        $paths = new ConfigPathResolver('C:/work', ['APPDATA' => 'C:/Users/me/AppData/Roaming'], 'Windows');
        self::assertSame('C:/Users/me/AppData/Roaming/rarus-echo/config.json', $paths->resolve());
        self::assertSame('D:/личный конфиг.json', $paths->resolve('D:/личный конфиг.json'));
    }

    public function testMissingUserDirectoryRequiresExplicitPath(): void
    {
        $paths = new ConfigPathResolver('/work', [], 'Linux');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('--config');
        $paths->resolve();
    }

    public function testRemoteConfigIsRejected(): void
    {
        $paths = new ConfigPathResolver('/work', [], 'Linux');
        $this->expectException(\InvalidArgumentException::class);
        $paths->resolve('https://example.com/secrets.json');
    }
}
