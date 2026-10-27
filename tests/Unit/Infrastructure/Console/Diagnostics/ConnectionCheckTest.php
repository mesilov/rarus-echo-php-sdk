<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Diagnostics;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Credentials;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConnectionCheck;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConnectionCheckTest extends TestCase
{
    private function credentials(): Credentials
    {
        return Credentials::fromString('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 'https://echo.example');
    }

    public function testAuthenticatedQueueReadUsesBoundedTimeoutAndNoRedirects(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            ++$calls;
            self::assertSame('GET', $method);
            self::assertSame('https://echo.example/v1/async/transcription/queue', $url);
            self::assertSame(10.0, $options['timeout']);
            self::assertSame(10.0, $options['max_duration']);
            self::assertSame(0, $options['max_redirects']);
            self::assertStringContainsString('11111111-1111-4111-8111-111111111111', implode(' ', $options['headers']));
            self::assertStringContainsString('22222222-2222-4222-8222-222222222222', implode(' ', $options['headers']));

            return new MockResponse('{"results":[{"files_count":0,"files_size":0,"files_duration":0}]}');
        });
        $result = (new ConnectionCheck($http))->run($this->credentials());
        self::assertSame('ok', $result->status);
        self::assertSame('connection_ok', $result->code);
        self::assertSame(1, $calls);
    }

    public function testNumericQueueCountersRemainCompatibleWithSdk(): void
    {
        $http = new MockHttpClient(new MockResponse('{"results":[{"files_count":"2","files_size":1.5,"files_duration":"0.5"}]}'));
        self::assertSame('ok', (new ConnectionCheck($http))->run($this->credentials())->status);
    }

    /** @return iterable<string, array{int, string}> */
    public static function errors(): iterable
    {
        yield 'unauthorized' => [401, 'authentication_failed'];
        yield 'forbidden' => [403, 'authentication_failed'];
        yield 'server' => [500, 'server_error'];
        yield 'unavailable' => [503, 'server_error'];
        yield 'validation' => [422, 'http_error'];
        yield 'wrong endpoint' => [404, 'http_error'];
        yield 'redirect' => [302, 'http_error'];
    }

    #[DataProvider('errors')]
    public function testHttpErrorsAreCategorizedWithoutRemoteContents(int $status, string $code): void
    {
        $http = new MockHttpClient(new MockResponse('{"detail":"RecognizableSecret"}', ['http_code' => $status]));
        $result = (new ConnectionCheck($http))->run($this->credentials());
        self::assertSame('error', $result->status);
        self::assertSame($code, $result->code);
        self::assertSame((string) $status, $result->details['http_status']);
        self::assertStringNotContainsString('RecognizableSecret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    #[DataProvider('errors')]
    public function testNonJsonErrorBodiesKeepHttpFailureCategory(int $status, string $code): void
    {
        $http = new MockHttpClient(new MockResponse('<html>RecognizableSecret proxy error</html>', ['http_code' => $status]));
        $result = (new ConnectionCheck($http))->run($this->credentials());
        self::assertSame($code, $result->code);
        self::assertSame((string) $status, $result->details['http_status']);
        self::assertStringNotContainsString('RecognizableSecret', $result->message);
    }

    public function testNetworkFailureIsSanitized(): void
    {
        $http = new MockHttpClient(static fn (): never => throw new TransportException('RecognizableSecret timeout'));
        $result = (new ConnectionCheck($http))->run($this->credentials());
        self::assertSame('network_error', $result->code);
        self::assertStringNotContainsString('RecognizableSecret', $result->message);
    }

    /** @return iterable<string, array{string}> */
    public static function badResponses(): iterable
    {
        yield 'html' => ['<html>RecognizableSecret</html>'];
        yield 'wrong API' => ['{}'];
        yield 'invalid counters' => ['{"results":[{"files_count":"x","files_size":0,"files_duration":0}]}'];
    }

    #[DataProvider('badResponses')]
    public function testUnexpectedResponseIsNotReportedAsReady(string $body): void
    {
        $result = (new ConnectionCheck(new MockHttpClient(new MockResponse($body))))->run($this->credentials());
        self::assertSame('error', $result->status);
        self::assertSame('unexpected_response', $result->code);
        self::assertStringNotContainsString('RecognizableSecret', $result->message);
    }

    public function testMissingCredentialsSkipRequest(): void
    {
        $http = new MockHttpClient(static fn (): never => throw new \LogicException('Must not make a request'));
        $result = (new ConnectionCheck($http))->run(null);
        self::assertSame('skipped', $result->status);
        self::assertSame('connection_skipped', $result->code);
    }
}
