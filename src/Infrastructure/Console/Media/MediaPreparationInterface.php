<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal */
interface MediaPreparationInterface
{
    /**
     * @param list<string> $files
     * @param \Closure(string): void $progress
     */
    public function prepare(array $files, MediaOptions $options, \Closure $progress): PreparedFileSet;

    public function cancel(): void;
}
