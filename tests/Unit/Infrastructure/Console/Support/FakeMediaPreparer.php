<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Support;

use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Rarus\Echo\Infrastructure\Console\Media\MediaPreparationInterface;
use Rarus\Echo\Infrastructure\Console\Media\PreparedFileSet;

final class FakeMediaPreparer implements MediaPreparationInterface
{
    public ?MediaOptions $options = null;
    public bool $cancelled = false;
    public ?\Closure $onPrepare = null;

    public function __construct(public PreparedFileSet $set = new PreparedFileSet())
    {
    }

    #[\Override]
    public function prepare(array $files, MediaOptions $options, \Closure $progress): PreparedFileSet
    {
        $this->options = $options;
        $progress('preparing: ' . $files[0]);
        if ($this->onPrepare instanceof \Closure) {
            ($this->onPrepare)();
        }

        return $this->set;
    }

    #[\Override]
    public function cancel(): void
    {
        $this->cancelled = true;
    }
}
