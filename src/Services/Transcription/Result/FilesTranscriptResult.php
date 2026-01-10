<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

use Rarus\Echo\Core\Pagination;

/**
 * Batch result with pagination
 */
final readonly class FilesTranscriptResult
{
    /**
     * @param array<int, FileItemTranscriptResult> $results
     */
    public function __construct(
        private array $results,
        public Pagination $pagination
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $results = array_map(
            static fn(array $item): FileItemTranscriptResult => FileItemTranscriptResult::fromArray($item),
            $data['results'] ?? []
        );

        return new self($results, Pagination::fromArray($data['pagination']));
    }

    /**
     * Get all results
     *
     * @return array<int, FileItemTranscriptResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
