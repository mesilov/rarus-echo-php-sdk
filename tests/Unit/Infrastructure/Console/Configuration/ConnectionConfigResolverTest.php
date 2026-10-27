<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Configuration;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfig;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;

final class ConnectionConfigResolverTest extends TestCase
{
    private string $directory;
    /** @var array<string, array{mixed, mixed, string|false}> */
    private array $previous = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-resolve-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        foreach (['RARUS_ECHO_API_KEY', 'RARUS_ECHO_USER_ID', 'RARUS_ECHO_BASE_URL', 'SYMFONY_DOTENV_VARS'] as $key) {
            $this->previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->previous as $key => [$env, $server, $process]) {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($env !== null) {
                $_ENV[$key] = $env;
            }
            if ($server !== null) {
                $_SERVER[$key] = $server;
            }
            putenv($process === false ? $key : $key . '=' . $process);
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    private function resolver(): ConnectionConfigResolver
    {
        return new ConnectionConfigResolver($this->directory, paths: new ConfigPathResolver($this->directory, ['HOME' => $this->directory], 'Linux'));
    }

    private function savedConfig(): string
    {
        $path = $this->directory . '/.config/rarus-echo/config.json';
        (new ConnectionConfigStore())->save($path, ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 'https://saved.example'));

        return $path;
    }

    public function testDefaultConfigIsUsedWithoutLegacyCredentials(): void
    {
        $this->savedConfig();
        self::assertSame('https://saved.example', $this->resolver()->resolve()->getBaseUrl());
        self::assertSame('default_config', $this->resolver()->resolveConnection()->source);
    }

    public function testExplicitConfigIgnoresAmbientCredentialsAndDotenv(): void
    {
        $path = $this->savedConfig();
        $_ENV['RARUS_ECHO_API_KEY'] = 'RecognizableFixtureSecret';
        file_put_contents($this->directory . '/.env', 'MALFORMED="unterminated');
        self::assertSame('https://saved.example', $this->resolver()->resolve($path)->getBaseUrl());
        self::assertSame('explicit_config', $this->resolver()->resolveConnection($path)->source);
    }

    public function testPartialEnvironmentNeverBorrowsFromDefaultConfig(): void
    {
        $this->savedConfig();
        $_ENV['RARUS_ECHO_API_KEY'] = '11111111-1111-4111-8111-111111111111';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_USER_ID');
        $this->resolver()->resolve();
    }

    public function testLegacyDotenvPrecedesSavedConfig(): void
    {
        $this->savedConfig();
        file_put_contents($this->directory . '/.env', "RARUS_ECHO_API_KEY=33333333-3333-4333-8333-333333333333\nRARUS_ECHO_USER_ID=44444444-4444-4444-8444-444444444444\nRARUS_ECHO_BASE_URL=https://env.example\n");
        self::assertSame('https://env.example', $this->resolver()->resolve()->getBaseUrl());
        self::assertSame('environment', $this->resolver()->resolveConnection()->source);
    }

    public function testExplicitMissingFileNeverFallsBack(): void
    {
        $this->savedConfig();
        $this->expectException(\RuntimeException::class);
        $this->resolver()->resolve($this->directory . '/missing.json');
    }
}
