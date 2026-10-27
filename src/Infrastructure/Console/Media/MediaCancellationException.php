<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal Unwinds active process/upload handles before explicit temporary cleanup. */
final class MediaCancellationException extends \RuntimeException
{
    public function __construct(public readonly int $exitCode)
    {
        parent::__construct('Media preparation cancelled.');
    }
}
