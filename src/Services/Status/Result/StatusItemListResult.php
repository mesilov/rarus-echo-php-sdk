<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Status\Result;

use Rarus\Echo\Core\Pagination;

/**
 * Batch status result with pagination
 */
final readonly class StatusItemListResult
{
    /**
     * @param array<int, StatusItemResult> $results
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
     * @throws \DateMalformedStringException
     */
    public static function fromArray(array $data): self
    {
        $results = array_map(
            static fn (array $item): StatusItemResult => StatusItemResult::fromArray($item),
            $data['results'] ?? []
        );

        $pagination = $data['pagination'] ?? [];

        return new self(
            results: $results,
            pagination: Pagination::fromArray($pagination)
        );
    }

    /**
     * Get all results
     *
     * @return array<int, StatusItemResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
