<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli\Contract;

use Rarus\Echo\Services\Queue\Result\QueueInfoResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Symfony\Component\Uid\Uuid;

interface EchoClientInterface
{
    public function getQueueInfo(): QueueInfoResult;

    public function getStatus(Uuid $fileId): StatusItemResult;

    public function getTranscript(Uuid $fileId): FileItemTranscriptResult;

    /**
     * @param list<string> $files
     */
    public function submit(array $files, TranscriptionOptions $options): TranscriptSubmitResult;
}
