<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli;

use Rarus\Echo\Cli\Contract\EchoClientInterface;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Symfony\Component\Uid\Uuid;

final readonly class SdkEchoClient implements EchoClientInterface
{
    public function __construct(private ServiceFactory $serviceFactory)
    {
    }

    #[\Override]
    public function getQueueInfo(): QueueInfoResult
    {
        return $this->serviceFactory->getQueueService()->getQueueInfo();
    }

    #[\Override]
    public function getStatus(Uuid $fileId): StatusItemResult
    {
        return $this->serviceFactory->getStatusService()->getByFileId($fileId);
    }

    #[\Override]
    public function getTranscript(Uuid $fileId): FileItemTranscriptResult
    {
        return $this->serviceFactory->getTranscriptionService()->getByFileId($fileId);
    }

    #[\Override]
    public function submit(array $files, TranscriptionOptions $options): TranscriptSubmitResult
    {
        return $this->serviceFactory->getTranscriptionService()->submit($files, $options);
    }
}
