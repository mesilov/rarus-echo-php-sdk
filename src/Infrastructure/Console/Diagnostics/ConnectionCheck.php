<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Diagnostics;

use Rarus\Echo\Core\Credentials;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal Read-only authenticated check with its own short transport deadline. */
final readonly class ConnectionCheck
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(private ?HttpClientInterface $httpClient = null)
    {
    }

    public function run(?Credentials $credentials): CheckResult
    {
        if (!$credentials instanceof Credentials) {
            return new CheckResult('skipped', 'connection_skipped', 'Connection check skipped: fix connection settings first.');
        }
        $details = ['timeout_seconds' => (string) self::TIMEOUT_SECONDS];

        try {
            $http = ($this->httpClient ?? HttpClient::create())->withOptions([
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS,
                'max_redirects' => 0,
            ]);
            $response = $http->request('GET', rtrim($credentials->getBaseUrl(), '/') . '/v1/async/transcription/queue', [
                'headers' => [
                    'Authorization' => $credentials->getApiKey()->toRfc4122(),
                    'user-id' => $credentials->getUserId()->toRfc4122(),
                    'Accept' => 'application/json',
                ],
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $response->cancel();

                return $this->httpFailure($status, $details);
            }
            $data = json_decode($response->getContent(false), true, flags: JSON_THROW_ON_ERROR);
            $queue = is_array($data) ? ($data['results'][0] ?? null) : null;
            if (!is_array($queue)) {
                throw new \UnexpectedValueException('Invalid queue response.');
            }
            foreach (['files_count', 'files_size', 'files_duration'] as $field) {
                if (!is_numeric($queue[$field] ?? null) || $queue[$field] < 0) {
                    throw new \UnexpectedValueException('Invalid queue response.');
                }
            }
        } catch (TransportExceptionInterface) {
            return new CheckResult('error', 'network_error', 'Cannot connect to Echo or the ten-second deadline expired. Check server URL, network and TLS settings.', $details);
        } catch (\Throwable) {
            return new CheckResult('error', 'unexpected_response', 'Echo did not return a valid queue response. Check server URL and API compatibility.', $details);
        }

        return new CheckResult('ok', 'connection_ok', 'Authenticated Echo queue request succeeded.', $details);
    }

    /** @param array<string, string> $details */
    private function httpFailure(int $status, array $details): CheckResult
    {
        $details['http_status'] = (string) $status;
        if (in_array($status, [401, 403], true)) {
            return new CheckResult('error', 'authentication_failed', 'Echo rejected authorization. Check API key, User ID and access permissions.', $details);
        }

        return new CheckResult('error', $status >= 500 ? 'server_error' : 'http_error', $status >= 500
            ? 'Echo returned a server error. Retry later or contact the server administrator.'
            : 'Echo returned an unexpected HTTP status. Check server URL and API access; redirects are not followed.', $details);
    }
}
