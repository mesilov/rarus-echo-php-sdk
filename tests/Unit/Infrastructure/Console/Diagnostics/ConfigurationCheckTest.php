<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Diagnostics;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfig;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConfigurationCheck;

final class ConfigurationCheckTest extends TestCase
{
    private string $directory;
    /** @var array<string, array{mixed, mixed, string|false}> */
    private array $previous = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-doctor-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        foreach (['RARUS_ECHO_API_KEY', 'RARUS_ECHO_USER_ID', 'RARUS_ECHO_BASE_URL', 'SYMFONY_DOTENV_VARS', 'XDG_CONFIG_HOME'] as $key) {
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

    private function check(): ConfigurationCheck
    {
        $paths = new ConfigPathResolver($this->directory, ['HOME' => $this->directory], 'Linux');

        return new ConfigurationCheck(new ConnectionConfigResolver($this->directory, paths: $paths), $paths);
    }

    public function testValidExplicitConfigReportsPathAndSourceWithoutCredentials(): void
    {
        $path = $this->directory . '/connection.json';
        (new ConnectionConfigStore())->save($path, ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222'));
        $_ENV['RARUS_ECHO_API_KEY'] = 'InvalidAmbientSecret';
        $result = $this->check()->run($path);
        self::assertSame('ok', $result->check->status);
        self::assertSame('explicit_config', $result->check->details['source']);
        self::assertSame($path, $result->check->details['path']);
        self::assertNotNull($result->credentials);
        self::assertStringNotContainsString('11111111', json_encode($result->check->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testMissingConfigHasErrorAndNoConnection(): void
    {
        $result = $this->check()->run($this->directory . '/missing.json');
        self::assertSame('error', $result->check->status);
        self::assertSame('missing_config', $result->check->code);
        self::assertNull($result->credentials);
    }

    public function testMissingDefaultFileIsWarningWithUsableEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '11111111-1111-4111-8111-111111111111';
        $_ENV['RARUS_ECHO_USER_ID'] = '22222222-2222-4222-8222-222222222222';
        $result = $this->check()->run();
        self::assertSame('warning', $result->check->status);
        self::assertSame('environment', $result->check->details['source']);
        self::assertNotNull($result->credentials);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidConfigs(): iterable
    {
        yield 'malformed' => ['{RecognizableSecret'];
        yield 'version' => ['{"version":2,"api_key":"RecognizableSecret"}'];
        yield 'types' => ['{"version":1,"api_key":4,"user_id":null,"base_url":false}'];
        yield 'uuid' => ['{"version":1,"api_key":"RecognizableSecret","user_id":"invalid","base_url":"https://example.com"}'];
        yield 'url' => ['{"version":1,"api_key":"11111111-1111-4111-8111-111111111111","user_id":"22222222-2222-4222-8222-222222222222","base_url":"not a url"}'];
    }

    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigIsSafeAndPreventsConnection(string $contents): void
    {
        $path = $this->directory . '/bad.json';
        file_put_contents($path, $contents);
        chmod($path, 0o600);
        $result = $this->check()->run($path);
        self::assertSame('error', $result->check->status);
        self::assertSame('invalid_config', $result->check->code);
        self::assertNull($result->credentials);
        self::assertStringNotContainsString('RecognizableSecret', json_encode($result->check->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testPartialEnvironmentDoesNotUseValidExplicitlyUnselectedFile(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = 'RecognizableSecret';
        $result = $this->check()->run();
        self::assertSame('error', $result->check->status);
        self::assertSame('invalid_connection', $result->check->code);
        self::assertNull($result->credentials);
    }

    public function testDotenvIsReportedAsEffectiveEnvironmentSource(): void
    {
        file_put_contents($this->directory . '/.env', "RARUS_ECHO_API_KEY=11111111-1111-4111-8111-111111111111\nRARUS_ECHO_USER_ID=22222222-2222-4222-8222-222222222222\n");
        $result = $this->check()->run();
        self::assertSame('environment', $result->check->details['source']);
        self::assertSame('warning', $result->check->status);
        self::assertNotNull($result->credentials);
    }

    public function testInvalidUnusedDefaultConfigDoesNotBlockValidEnvironment(): void
    {
        mkdir($this->directory . '/.config/rarus-echo', 0o700, true);
        $path = $this->directory . '/.config/rarus-echo/config.json';
        file_put_contents($path, '{RecognizableSecret');
        chmod($path, 0o600);
        $_ENV['RARUS_ECHO_API_KEY'] = '11111111-1111-4111-8111-111111111111';
        $_ENV['RARUS_ECHO_USER_ID'] = '22222222-2222-4222-8222-222222222222';
        $result = $this->check()->run();
        self::assertSame('warning', $result->check->status);
        self::assertSame('invalid_config', $result->check->details['file_status']);
        self::assertSame('environment', $result->check->details['source']);
        self::assertNotNull($result->credentials);
    }

    public function testMalformedDotenvReportsActiveSourceErrorAlongsideMissingFile(): void
    {
        file_put_contents($this->directory . '/.env', 'MALFORMED="RecognizableSecret');
        $result = $this->check()->run();
        self::assertSame('invalid_connection', $result->check->code);
        self::assertSame('environment', $result->check->details['source']);
        self::assertSame('missing_config', $result->check->details['file_status']);
        self::assertStringContainsString('.env', $result->check->message);
        self::assertStringNotContainsString('RecognizableSecret', $result->check->message);
    }

    public function testDotenvSelectedConfigPathIsThePathActuallyChecked(): void
    {
        $path = $this->directory . '/xdg/rarus-echo/config.json';
        (new ConnectionConfigStore())->save($path, ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222'));
        file_put_contents($this->directory . '/.env', 'XDG_CONFIG_HOME=' . $this->directory . '/xdg');
        $paths = new ConfigPathResolver($this->directory);
        $check = new ConfigurationCheck(new ConnectionConfigResolver($this->directory, paths: $paths), $paths);
        $result = $check->run();
        self::assertSame('ok', $result->check->status);
        self::assertSame($path, $result->check->details['path']);
        self::assertSame('default_config', $result->check->details['source']);
        self::assertSame('config_ok', $result->check->details['file_status']);
    }

    public function testUnreadableConfigurationIsReportedWithoutReadingContents(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            self::markTestSkipped('Requires unprivileged Unix permissions');
        }
        $path = $this->directory . '/unreadable.json';
        file_put_contents($path, 'RecognizableSecret');
        chmod($path, 0o000);

        try {
            $result = $this->check()->run($path);
            self::assertSame('error', $result->check->status);
            self::assertSame('invalid_config', $result->check->code);
            self::assertNull($result->credentials);
        } finally {
            chmod($path, 0o600);
        }
    }

    public function testUnsafeConfigIsRejected(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Unix permissions');
        }
        $path = $this->directory . '/unsafe.json';
        file_put_contents($path, '{}');
        chmod($path, 0o644);
        $result = $this->check()->run($path);
        self::assertSame('error', $result->check->status);
        self::assertNull($result->credentials);
    }
}
