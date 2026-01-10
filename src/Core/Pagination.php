<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use InvalidArgumentException;

/**
 * Immutable pagination value object
 */
final readonly class Pagination
{
    /**
     * @param int<1, max> $page Current page number (1-based)
     * @param int<1, max> $perPage Items per page
     *
     * @throws InvalidArgumentException If page or perPage is less than 1
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total = 0
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1');
        }

        if ($this->perPage < 1) {
            throw new InvalidArgumentException('Per page must be greater than or equal to 1');
        }
        if ($total < 0) {
            throw new InvalidArgumentException('Total must be greater than or equal to 0');
        }
    }

    /**
     * Create pagination with default values (page 1, 10 items per page)
     */
    public static function default(): self
    {
        return new self(page: 1, perPage: 10);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            page: (int)$data['page'],
            perPage: (int)$data['per_page'],
            total: (int)$data['total_pages']
        );
    }

    /**
     * Get offset for database queries or API requests
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Get limit (alias for perPage for clarity in some contexts)
     */
    public function getLimit(): int
    {
        return $this->perPage;
    }

    /**
     * Convert to query parameters array
     * Use this when pagination is passed via URL query string
     *
     * @return array<string, int>
     */
    public function toQueryParams(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * Convert to headers array
     * Use this when pagination is passed via HTTP headers
     *
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        return [
            'page' => (string)$this->page,
            'per_page' => (string)$this->perPage,
        ];
    }
}
