<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli\Support;

use Rarus\Echo\Cli\Contract\EchoClientInterface;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Symfony\Component\Uid\Uuid;

final class FakeEchoClient implements EchoClientInterface
{
    public ?QueueInfoResult $queueInfo = null;
    public ?StatusItemResult $status = null;
    public ?FileItemTranscriptResult $transcript = null;
    public ?TranscriptSubmitResult $submitResult = null;
    public ?\Throwable $exception = null;
    public ?Uuid $lastStatusFileId = null;
    public ?Uuid $lastTranscriptFileId = null;
    /** @var list<string> */
    public array $lastSubmittedFiles = [];
    public ?TranscriptionOptions $lastTranscriptionOptions = null;

    #[\Override]
    public function getQueueInfo(): QueueInfoResult
    {
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->queueInfo ?? throw new \LogicException('Queue info result was not configured.');
    }

    #[\Override]
    public function getStatus(Uuid $fileId): StatusItemResult
    {
        $this->lastStatusFileId = $fileId;

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->status ?? throw new \LogicException('Status result was not configured.');
    }

    #[\Override]
    public function getTranscript(Uuid $fileId): FileItemTranscriptResult
    {
        $this->lastTranscriptFileId = $fileId;

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->transcript ?? throw new \LogicException('Transcript result was not configured.');
    }

    #[\Override]
    public function submit(array $files, TranscriptionOptions $options): TranscriptSubmitResult
    {
        $this->lastSubmittedFiles = $files;
        $this->lastTranscriptionOptions = $options;

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->submitResult ?? throw new \LogicException('Submit result was not configured.');
    }
}
