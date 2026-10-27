<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal */
final readonly class Toolkit
{
    public function __construct(public string $ffmpeg, public string $ffprobe)
    {
    }
}
