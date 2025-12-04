<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Request;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Request for getting transcriptions by period
 */
final class PeriodRequest
{
    public function __construct(
        private readonly string $periodStart = '2000-01-01',
        private readonly string $periodEnd = '2099-12-31',
        private readonly string $timeStart = '00:00:00',
        private readonly string $timeEnd = '23:59:59',
        private readonly int $page = 1,
        private readonly int $perPage = 10
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1');
        }

        if ($this->perPage < 1) {
            throw new InvalidArgumentException('Per page must be greater than or equal to 1');
        }
    }

    /**
     * Create request for today
     */
    public static function today(): self
    {
        $today = date('Y-m-d');

        return new self(
            periodStart: $today,
            periodEnd: $today
        );
    }

    /**
     * Create request for date range
     */
    public static function dateRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        int $page = 1,
        int $perPage = 10
    ): self {
        return new self(
            periodStart: $start->format('Y-m-d'),
            periodEnd: $end->format('Y-m-d'),
            page: $page,
            perPage: $perPage
        );
    }

    public function getPeriodStart(): string
    {
        return $this->periodStart;
    }

    public function getPeriodEnd(): string
    {
        return $this->periodEnd;
    }

    public function getTimeStart(): string
    {
        return $this->timeStart;
    }

    public function getTimeEnd(): string
    {
        return $this->timeEnd;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Convert to query parameters
     *
     * @return array<string, string|int>
     */
    public function toQueryParams(): array
    {
        return [
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'time_start' => $this->timeStart,
            'time_end' => $this->timeEnd,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
