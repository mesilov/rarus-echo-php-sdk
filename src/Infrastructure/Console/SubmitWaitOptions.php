<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

final readonly class SubmitWaitOptions
{
    public function __construct(
        public bool $wait,
        public int $pollIntervalSeconds,
        public int $timeoutSeconds,
        public bool $rawResult,
        public ?string $outputPath
    ) {
    }
}
