<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal */
final readonly class PreparationPlan
{
    /** @param list<string> $arguments */
    public function __construct(public string $extension, public string $codec, public array $arguments, public float $duration, public ?int $bitsPerSample = null)
    {
    }
}
