<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use Carbon\CarbonPeriod;
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
     * @param CarbonPeriod $period     Date period (start and end dates)
     * @param string       $timeStart  Start time (default: '00:00:00')
     * @param string       $timeEnd    End time (default: '23:59:59')
     * @param int          $page       Page number (default: 1)
     * @param int          $perPage    Items per page (default: 10)
     */
    public function getTranscriptsByPeriod(
        CarbonPeriod $period,
        string $timeStart = '00:00:00',
        string $timeEnd = '23:59:59',
        int $page = 1,
        int $perPage = 10
    ): TranscriptBatchResult;

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
