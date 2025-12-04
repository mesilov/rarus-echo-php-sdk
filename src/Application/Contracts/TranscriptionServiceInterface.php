<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use Rarus\Echo\Services\Transcription\Request\DriveRequest;
use Rarus\Echo\Services\Transcription\Request\PeriodRequest;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\TranscriptBatchResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptItemResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptPostResult;
use Rarus\Echo\Services\Transcription\Result\WebDAVResult;

/**
 * Contract for Transcription service
 */
interface TranscriptionServiceInterface
{
    /**
     * Submit files for transcription
     *
     * @param array<string>        $files
     * @param TranscriptionOptions $options
     */
    public function submitTranscription(
        array $files,
        TranscriptionOptions $options
    ): TranscriptPostResult;

    /**
     * Get transcription result by file ID
     */
    public function getTranscript(string $fileId): TranscriptItemResult;

    /**
     * Get transcriptions by period
     */
    public function getTranscriptsByPeriod(PeriodRequest $request): TranscriptBatchResult;

    /**
     * Get transcriptions by list of file IDs
     *
     * @param array<string> $fileIds
     */
    public function getTranscriptsList(
        array $fileIds,
        int $page = 1,
        int $perPage = 10
    ): TranscriptBatchResult;

    /**
     * Submit files from Rarus Drive for transcription
     */
    public function submitFromDrive(DriveRequest $request): WebDAVResult;
}
