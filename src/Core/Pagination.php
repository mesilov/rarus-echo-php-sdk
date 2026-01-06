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
     * @param int $page    Current page number (1-based)
     * @param int $perPage Items per page
     *
     * @throws InvalidArgumentException If page or perPage is less than 1
     */
    public function __construct(
        public int $page,
        public int $perPage
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1');
        }

        if ($this->perPage < 1) {
            throw new InvalidArgumentException('Per page must be greater than or equal to 1');
        }
    }

    /**
     * Create pagination with default values (page 1, 10 items per page)
     */
    public static function default(): self
    {
        return new self(page: 1, perPage: 10);
    }

    /**
     * Create pagination for the first page with specified items per page
     */
    public static function firstPage(int $perPage = 10): self
    {
        return new self(page: 1, perPage: $perPage);
    }

    /**
     * Create pagination with custom values
     */
    public static function create(int $page, int $perPage): self
    {
        return new self($page, $perPage);
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
     * Create a new pagination for the next page
     */
    public function next(): self
    {
        return new self(page: $this->page + 1, perPage: $this->perPage);
    }

    /**
     * Create a new pagination for the previous page
     *
     * @throws InvalidArgumentException If already on the first page
     */
    public function previous(): self
    {
        if ($this->page === 1) {
            throw new InvalidArgumentException('Cannot go to previous page, already on page 1');
        }

        return new self(page: $this->page - 1, perPage: $this->perPage);
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
            'page' => (string) $this->page,
            'per_page' => (string) $this->perPage,
        ];
    }
}
