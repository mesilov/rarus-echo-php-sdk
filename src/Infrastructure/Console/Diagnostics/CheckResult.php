<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Diagnostics;

/** @internal Only sanitized diagnostic data belongs in a report. */
final readonly class CheckResult
{
    /** @param array<string, string> $details */
    public function __construct(
        public string $status,
        public string $code,
        public string $message,
        public array $details = []
    ) {
    }

    /** @return array{status: string, code: string, message: string, details: object} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'code' => $this->code, 'message' => $this->message, 'details' => (object) $this->details];
    }
}
