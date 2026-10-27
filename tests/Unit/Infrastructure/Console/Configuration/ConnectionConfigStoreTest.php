<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Configuration;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigFileProtection;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfig;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;

final class ConnectionConfigStoreTest extends TestCase
{
    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-config-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testRoundTripPrivateConfigAndRefusedOverwrite(): void
    {
        $path = $this->directory . '/private/конфиг.json';
        $store = new ConnectionConfigStore();
        $config = ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 'https://example.com');
        $store->save($path, $config);
        self::assertSame($config->credentials()->getApiKey()->toRfc4122(), $store->load($path)->credentials()->getApiKey()->toRfc4122());
        self::assertSame(0o600, fileperms($path) & 0o777);
        self::assertSame(0o700, fileperms(dirname($path)) & 0o777);
        $original = file_get_contents($path);

        try {
            $store->save($path, $config);
            self::fail('Existing config must require explicit replacement.');
        } catch (\RuntimeException) {
            self::assertSame($original, file_get_contents($path));
        }
    }

    public function testCompetingFirstWriterIsNeverOverwritten(): void
    {
        $path = $this->directory . '/race.json';
        $protection = new class ($path) extends ConfigFileProtection {
            public function __construct(private readonly string $destination)
            {
            }

            public function protect(string $path): void
            {
                parent::protect($path);
                if (is_file($path)) {
                    file_put_contents($this->destination, 'competing connection');
                    chmod($this->destination, 0o600);
                }
            }
        };
        $config = ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222');

        try {
            (new ConnectionConfigStore($protection))->save($path, $config);
            self::fail('Concurrent destination must not be replaced.');
        } catch (\RuntimeException) {
            self::assertSame('competing connection', file_get_contents($path));
            self::assertSame([], glob($this->directory . '/.rarus-echo-*.tmp'));
        }
    }

    public function testInvalidValuesNeverAppearInErrors(): void
    {
        $secret = 'RecognizableFixtureSecret';

        try {
            ConnectionConfig::fromValues($secret, 'bad-user');
            self::fail('Invalid credentials must be rejected.');
        } catch (\InvalidArgumentException $error) {
            self::assertStringNotContainsString($secret, $error->getMessage());
        }
    }

    public function testProtectionFailurePreservesPreviousConfigAndCleansTemporaryFile(): void
    {
        $path = $this->directory . '/config.json';
        file_put_contents($path, 'previous connection');
        chmod($path, 0o600);
        $protection = new class () extends ConfigFileProtection {
            public function protect(string $path): void
            {
                throw new \RuntimeException('Simulated protection failure.');
            }
        };
        $config = ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222');

        try {
            (new ConnectionConfigStore($protection))->save($path, $config, true);
            self::fail('Unprotected temporary file must never be committed.');
        } catch (\RuntimeException) {
            self::assertSame('previous connection', file_get_contents($path));
            self::assertSame([], glob($this->directory . '/.rarus-echo-*.tmp'));
        }
    }

    public function testUnsafeAndMalformedFilesAreRejectedWithoutContents(): void
    {
        $path = $this->directory . '/config.json';
        file_put_contents($path, '{"api_key":"RecognizableFixtureSecret"');
        chmod($path, 0o600);

        try {
            (new ConnectionConfigStore())->load($path);
            self::fail('Malformed JSON must be rejected.');
        } catch (\RuntimeException $error) {
            self::assertStringNotContainsString('RecognizableFixtureSecret', $error->getMessage());
        }
        chmod($path, 0o644);
        $this->expectException(\RuntimeException::class);
        (new ConnectionConfigStore())->load($path);
    }

    public function testSymlinkIsRejectedAndTargetPreserved(): void
    {
        $target = $this->directory . '/original';
        file_put_contents($target, 'original');
        symlink($target, $this->directory . '/link');

        try {
            (new ConnectionConfigStore())->load($this->directory . '/link');
            self::fail('Symlink must be rejected.');
        } catch (\RuntimeException) {
            self::assertSame('original', file_get_contents($target));
        }
    }
}
