<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

/**
 * Batch result with pagination
 */
final readonly class TranscriptBatchResult
{
    /**
     * @param array<int, TranscriptItemResult> $results
     */
    public function __construct(
        private array $results,
        private int $page,
        private int $perPage,
        private int $totalPages
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
            fn (array $item): TranscriptItemResult => TranscriptItemResult::fromArray($item),
            $data['results'] ?? []
        );

        $pagination = $data['pagination'] ?? [];

        return new self(
            results: $results,
            page: $pagination['page'] ?? 1,
            perPage: $pagination['per_page'] ?? 10,
            totalPages: $pagination['total_pages'] ?? 1
        );
    }

    /**
     * Get all results
     *
     * @return array<int, TranscriptItemResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get first result
     */
    public function getFirst(): ?TranscriptItemResult
    {
        return $this->results[0] ?? null;
    }

    /**
     * Get count of results
     */
    public function getCount(): int
    {
        return count($this->results);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * Check if there is next page
     */
    public function hasNextPage(): bool
    {
        return $this->page < $this->totalPages;
    }

    /**
     * Check if there is previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }
}
