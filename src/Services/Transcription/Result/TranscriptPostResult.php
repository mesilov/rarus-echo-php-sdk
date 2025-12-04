<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

/**
 * Result of submitting file for transcription
 * Contains file_id that can be used to retrieve transcription result
 */
final class TranscriptPostResult
{
    /**
     * @param array<int, array{file_id: string}> $results
     */
    public function __construct(
        private readonly array $results
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['results'] ?? []);
    }

    /**
     * Get all file IDs
     *
     * @return array<string>
     */
    public function getFileIds(): array
    {
        return array_map(
            fn (array $item) => $item['file_id'],
            $this->results
        );
    }

    /**
     * Get first file ID (for single file upload)
     */
    public function getFirstFileId(): ?string
    {
        return $this->results[0]['file_id'] ?? null;
    }

    /**
     * Get raw results
     *
     * @return array<int, array{file_id: string}>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get count of submitted files
     */
    public function getCount(): int
    {
        return count($this->results);
    }
}
