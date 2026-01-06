<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

/**
 * Result of submitting file for transcription
 * Contains file_id that can be used to retrieve transcription result
 */
final readonly class TranscriptPostResult
{
    /**
     * @param array<int, array{file_id: string}> $results
     */
    public function __construct(
        private array $results
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['results'])) {
            throw new \InvalidArgumentException('Missing required field: results');
        }

        if (!is_array($data['results'])) {
            throw new \InvalidArgumentException('Field "results" must be an array');
        }

        // Validate structure of each result item
        foreach ($data['results'] as $index => $result) {
            if (!is_array($result) || !isset($result['file_id'])) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid result structure at index %d: missing file_id', $index)
                );
            }
        }

        return new self($data['results']);
    }

    /**
     * Get all file IDs
     *
     * @return array<string>
     */
    public function getFileIds(): array
    {
        return array_map(
            fn (array $item): string => $item['file_id'],
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
