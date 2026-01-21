<?php

declare(strict_types=1);

namespace Rarus\Echo;

use DateTimeInterface;

/**
 * Utility class for formatting DateTime objects for API requests
 */
final class DateTimeFormatter
{
    /**
     * Convert DateTime range to query parameters array
     * Used for filtering API requests by date/time period
     *
     * @param DateTimeInterface $start Start date/time
     * @param DateTimeInterface $end   End date/time
     *
     * @return array<string, string> Query parameters with date and time fields
     */
    public static function toQueryParams(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return [
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'time_start' => $start->format('H:i:s'),
            'time_end' => $end->format('H:i:s'),
        ];
    }
}
