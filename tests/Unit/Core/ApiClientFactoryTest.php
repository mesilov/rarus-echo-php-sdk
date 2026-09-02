<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\ApiClientFactory;
use Rarus\Echo\Core\Credentials;
use Rarus\Echo\Core\Response\ResponseHandler;

final class ApiClientFactoryTest extends TestCase
{
    private Credentials $credentials;

    #[\Override]
    protected function setUp(): void
    {
        $this->credentials = Credentials::fromString(
            '12345678-1234-1234-1234-123456789abc',
            '87654321-4321-4321-4321-987654321cba'
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up environment variables
        unset(
            $_ENV['RARUS_ECHO_API_KEY'],
            $_ENV['RARUS_ECHO_USER_ID'],
            $_ENV['RARUS_ECHO_BASE_URL'],
            $_ENV[ApiClientFactory::HTTP_TIMEOUT_ENV],
            $_SERVER['RARUS_ECHO_API_KEY'],
            $_SERVER['RARUS_ECHO_USER_ID'],
            $_SERVER['RARUS_ECHO_BASE_URL'],
            $_SERVER[ApiClientFactory::HTTP_TIMEOUT_ENV]
        );
    }

    public function testCreateWithCredentials(): void
    {
        $apiClientFactory = new ApiClientFactory($this->credentials);

        $this->assertInstanceOf(ApiClientFactory::class, $apiClientFactory);
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '12345678-1234-1234-1234-123456789abc';
        $_ENV['RARUS_ECHO_USER_ID'] = '87654321-4321-4321-4321-987654321cba';

        $apiClientFactory = ApiClientFactory::fromEnvironment();

        $this->assertInstanceOf(ApiClientFactory::class, $apiClientFactory);
    }

    public function testFromEnvironmentThrowsExceptionWhenApiKeyNotSet(): void
    {
        unset($_ENV['RARUS_ECHO_API_KEY'], $_SERVER['RARUS_ECHO_API_KEY']);
        $_ENV['RARUS_ECHO_USER_ID'] = '87654321-4321-4321-4321-987654321cba';
        $_SERVER['RARUS_ECHO_USER_ID'] = '87654321-4321-4321-4321-987654321cba';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_API_KEY');

        ApiClientFactory::fromEnvironment();
    }

    public function testFromEnvironmentThrowsExceptionWhenUserIdNotSet(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '12345678-1234-1234-1234-123456789abc';
        $_SERVER['RARUS_ECHO_API_KEY'] = '12345678-1234-1234-1234-123456789abc';
        unset($_ENV['RARUS_ECHO_USER_ID'], $_SERVER['RARUS_ECHO_USER_ID']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_USER_ID');

        ApiClientFactory::fromEnvironment();
    }

    public function testBuildWithDefaults(): void
    {
        $apiClientFactory = new ApiClientFactory($this->credentials);
        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
        $this->assertSame($this->credentials, $apiClient->getCredentials());
    }

    public function testBuildWithCustomHttpClient(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $apiClientFactory = (new ApiClientFactory($this->credentials))
            ->withHttpClient($httpClient);

        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testBuildWithCustomRequestFactory(): void
    {
        $requestFactory = $this->createMock(RequestFactoryInterface::class);

        $apiClientFactory = (new ApiClientFactory($this->credentials))
            ->withRequestFactory($requestFactory);

        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testBuildWithCustomStreamFactory(): void
    {
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $apiClientFactory = (new ApiClientFactory($this->credentials))
            ->withStreamFactory($streamFactory);

        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testBuildWithCustomLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $apiClientFactory = (new ApiClientFactory($this->credentials))
            ->withLogger($logger);

        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testBuildWithCustomResponseHandler(): void
    {
        $responseHandler = new ResponseHandler();

        $apiClientFactory = (new ApiClientFactory($this->credentials))
            ->withResponseHandler($responseHandler);

        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testFluentInterface(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $apiClient = (new ApiClientFactory($this->credentials))
            ->withHttpClient($httpClient)
            ->withLogger($logger)
            ->withRequestFactory($requestFactory)
            ->withStreamFactory($streamFactory)
            ->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
        $this->assertSame($this->credentials, $apiClient->getCredentials());
    }

    public function testBuildReturnsApiClientInstance(): void
    {
        $apiClientFactory = new ApiClientFactory($this->credentials);
        $apiClient = $apiClientFactory->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testWithHttpTimeoutReturnsSelf(): void
    {
        $apiClientFactory = new ApiClientFactory($this->credentials);

        $this->assertSame($apiClientFactory, $apiClientFactory->withHttpTimeout(120));
    }

    public function testBuildWithCustomHttpTimeout(): void
    {
        $apiClient = (new ApiClientFactory($this->credentials))
            ->withHttpTimeout(300)
            ->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    #[DataProvider('nonPositiveTimeoutProvider')]
    public function testWithHttpTimeoutRejectsNonPositiveValues(int $seconds): void
    {
        $apiClientFactory = new ApiClientFactory($this->credentials);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP timeout must be a positive number of seconds');

        $apiClientFactory->withHttpTimeout($seconds);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveTimeoutProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testBuildAcceptsHttpTimeoutFromEnvironment(): void
    {
        $_ENV[ApiClientFactory::HTTP_TIMEOUT_ENV] = '900';

        $apiClient = (new ApiClientFactory($this->credentials))->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    #[DataProvider('invalidEnvironmentTimeoutProvider')]
    public function testBuildRejectsInvalidHttpTimeoutFromEnvironment(string $value): void
    {
        $_ENV[ApiClientFactory::HTTP_TIMEOUT_ENV] = $value;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(ApiClientFactory::HTTP_TIMEOUT_ENV);

        (new ApiClientFactory($this->credentials))->build();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEnvironmentTimeoutProvider(): array
    {
        return [
            'zero' => ['0'],
            'non-numeric' => ['abc'],
            'float' => ['12.5'],
            'negative' => ['-30'],
        ];
    }

    public function testExplicitHttpTimeoutOverridesEnvironment(): void
    {
        // An invalid env value must be ignored when an explicit timeout is set,
        // proving the explicit value takes precedence over the environment.
        $_ENV[ApiClientFactory::HTTP_TIMEOUT_ENV] = 'not-a-number';

        $apiClient = (new ApiClientFactory($this->credentials))
            ->withHttpTimeout(300)
            ->build();

        $this->assertInstanceOf(ApiClient::class, $apiClient);
    }

    public function testFactoryMethodsReturnSelf(): void
    {
        $apiClientFactory = new ApiClientFactory($this->credentials);

        $result1 = $apiClientFactory->withHttpClient($this->createMock(ClientInterface::class));
        $this->assertSame($apiClientFactory, $result1);

        $result2 = $apiClientFactory->withLogger($this->createMock(LoggerInterface::class));
        $this->assertSame($apiClientFactory, $result2);

        $result3 = $apiClientFactory->withRequestFactory($this->createMock(RequestFactoryInterface::class));
        $this->assertSame($apiClientFactory, $result3);

        $result4 = $apiClientFactory->withStreamFactory($this->createMock(StreamFactoryInterface::class));
        $this->assertSame($apiClientFactory, $result4);

        $result5 = $apiClientFactory->withResponseHandler(new ResponseHandler());
        $this->assertSame($apiClientFactory, $result5);
    }
}
