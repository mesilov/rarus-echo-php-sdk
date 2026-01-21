<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

use Symfony\Component\Uid\Uuid;

/**
 * Result of submitting file for transcription
 * Contains file_id that can be used to retrieve transcription result
 */
final readonly class TranscriptSubmitResult
{
    /**
     * @param array<int, Uuid> $fileIds
     */
    public function __construct(
        private array $fileIds
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
        $items = [];
        foreach ($data['results'] as $index => $result) {
            if (!is_array($result) || !isset($result['file_id'])) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid result structure at index %d: missing file_id', $index)
                );
            }

            $items[] = Uuid::fromString($result['file_id']);
        }

        return new self($items);
    }

    /**
     * Get all file IDs
     *
     * @return Uuid[]
     */
    public function getFileIds(): array
    {
        return $this->fileIds;
    }
}
