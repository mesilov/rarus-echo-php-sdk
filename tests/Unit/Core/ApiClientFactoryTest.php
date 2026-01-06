<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Core;

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
            $_SERVER['RARUS_ECHO_API_KEY'],
            $_SERVER['RARUS_ECHO_USER_ID'],
            $_SERVER['RARUS_ECHO_BASE_URL']
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
