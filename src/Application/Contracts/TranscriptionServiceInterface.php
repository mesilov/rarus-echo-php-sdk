<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use DateTimeInterface;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Services\Transcription\Request\DriveRequest;
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
     *
     * @param DateTimeInterface $startDate  Start date and time
     * @param DateTimeInterface $endDate    End date and time
     * @param Pagination        $pagination Pagination settings
     */
    public function getTranscriptsByPeriod(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Pagination $pagination
    ): TranscriptBatchResult;

    /**
     * Get transcriptions by list of file IDs
     *
     * @param array<string> $fileIds    Array of file IDs
     * @param Pagination    $pagination Pagination settings
     */
    public function getTranscriptsList(
        array $fileIds,
        Pagination $pagination
    ): TranscriptBatchResult;

    /**
     * Submit files from Rarus Drive for transcription
     */
    public function submitFromDrive(DriveRequest $request): WebDAVResult;
}
