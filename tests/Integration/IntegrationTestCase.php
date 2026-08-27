<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rarus\Echo\Services\ServiceFactory;
use Symfony\Component\Uid\Uuid;

abstract class IntegrationTestCase extends TestCase
{
    private const string PLACEHOLDER_API_KEY = 'your-api-key-here';
    private const string PLACEHOLDER_USER_ID = '00000000-0000-0000-0000-000000000000';

    final protected function createServiceFactory(): ServiceFactory
    {
        $this->requireEchoCredentials();

        return ServiceFactory::fromEnvironment(new NullLogger());
    }

    final protected function testAudioPath(string $fileName): string
    {
        $filePath = dirname(__DIR__) . '/Assets/ru/' . $fileName;

        if (!file_exists($filePath)) {
            $this->markTestSkipped(sprintf('Test audio file not found: %s', $filePath));
        }

        return $filePath;
    }

    /**
     * @return list<string>
     */
    final protected function testAudioFiles(string ...$fileNames): array
    {
        $files = [];
        foreach ($fileNames as $fileName) {
            $files[] = $this->testAudioPath($fileName);
        }

        return $files;
    }

    final protected function requireEchoCredentials(): void
    {
        $apiKey = $this->getEnvironmentVariable('RARUS_ECHO_API_KEY');
        $userId = $this->getEnvironmentVariable('RARUS_ECHO_USER_ID');

        if ($apiKey === '' || $apiKey === self::PLACEHOLDER_API_KEY) {
            $this->markTestSkipped('Integration tests require a real RARUS_ECHO_API_KEY value.');
        }

        if ($userId === '' || $userId === self::PLACEHOLDER_USER_ID) {
            $this->markTestSkipped('Integration tests require a real RARUS_ECHO_USER_ID value.');
        }

        $this->assertValidUuidEnvironmentValue('RARUS_ECHO_API_KEY', $apiKey);
        $this->assertValidUuidEnvironmentValue('RARUS_ECHO_USER_ID', $userId);

        $this->setEnvironmentVariable('RARUS_ECHO_API_KEY', $apiKey);
        $this->setEnvironmentVariable('RARUS_ECHO_USER_ID', $userId);

        $baseUrl = $this->getEnvironmentVariable('RARUS_ECHO_BASE_URL');
        if ($baseUrl !== '') {
            $this->setEnvironmentVariable('RARUS_ECHO_BASE_URL', $baseUrl);
        }
    }

    private function getEnvironmentVariable(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function setEnvironmentVariable(string $name, string $value): void
    {
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function assertValidUuidEnvironmentValue(string $name, string $value): void
    {
        try {
            Uuid::fromString($value);
        } catch (\Throwable) {
            $this->fail(sprintf('%s must be a valid UUID for integration tests.', $name));
        }
    }
}
