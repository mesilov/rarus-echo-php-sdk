<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

use Rarus\Echo\Contracts\EchoClientInterface;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Symfony\Component\Uid\Uuid;

final readonly class TranscriptPoller
{
    private \Closure $sleep;

    private \Closure $time;

    public function __construct(?\Closure $sleep = null, ?\Closure $time = null)
    {
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
        $this->time = $time ?? static fn (): int => time();
    }

    /**
     * @param list<Uuid> $fileIds
     *
     * @return list<FileItemTranscriptResult>
     */
    public function wait(
        EchoClientInterface $client,
        array $fileIds,
        int $pollIntervalSeconds,
        int $timeoutSeconds,
        callable $progress
    ): array {
        $deadline = ($this->time)() + $timeoutSeconds;
        /** @var array<string, Uuid> $pending */
        $pending = [];
        $orderedFileIds = [];

        foreach ($fileIds as $fileId) {
            $fileIdString = $fileId->toRfc4122();
            $pending[$fileIdString] = $fileId;
            $orderedFileIds[] = $fileIdString;
        }

        /** @var array<string, FileItemTranscriptResult> $results */
        $results = [];
        /** @var array<string, string> $lastStatuses */
        $lastStatuses = [];
        $attempt = 0;

        while ($pending !== []) {
            ++$attempt;

            foreach ($pending as $fileIdString => $fileId) {
                $transcript = $client->getTranscript($fileId);
                $status = $transcript->transcriptionStatus->value;
                $lastStatuses[$fileIdString] = $status;
                $progress(sprintf('polling: attempt=%d file_id=%s status=%s', $attempt, $fileIdString, $status));

                if ($transcript->isSuccessful()) {
                    $results[$fileIdString] = $transcript;
                    unset($pending[$fileIdString]);
                    $progress(sprintf('completed: %s status=%s', $fileIdString, $status));

                    continue;
                }

                if ($transcript->isFailed()) {
                    throw new \RuntimeException(sprintf(
                        'Transcript processing failed: file_id=%s status=%s',
                        $fileIdString,
                        $status
                    ));
                }
            }

            if ($pending === []) {
                break;
            }

            $remainingSeconds = $deadline - ($this->time)();
            if ($remainingSeconds <= 0) {
                throw new \RuntimeException($this->formatTimeoutMessage($timeoutSeconds, $orderedFileIds, $lastStatuses));
            }

            ($this->sleep)(min($pollIntervalSeconds, $remainingSeconds));
        }

        return array_map(
            static fn (string $fileId): FileItemTranscriptResult => $results[$fileId],
            $orderedFileIds
        );
    }

    /**
     * @param list<string>          $orderedFileIds
     * @param array<string, string> $lastStatuses
     */
    private function formatTimeoutMessage(int $timeoutSeconds, array $orderedFileIds, array $lastStatuses): string
    {
        $lines = [
            sprintf('Timeout after %d seconds while waiting for transcript results.', $timeoutSeconds),
        ];

        foreach ($orderedFileIds as $fileId) {
            if (!isset($lastStatuses[$fileId])) {
                continue;
            }

            $lines[] = sprintf('last_status: file_id=%s status=%s', $fileId, $lastStatuses[$fileId]);
        }

        return implode("\n", $lines);
    }
}
