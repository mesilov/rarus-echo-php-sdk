<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Support;

use Rarus\Echo\Contracts\EchoClientFactoryInterface;
use Rarus\Echo\Contracts\EchoClientInterface;

final class FailingEchoClientFactory implements EchoClientFactoryInterface
{
    public int $calls = 0;

    #[\Override]
    public function create(): EchoClientInterface
    {
        ++$this->calls;

        throw new \RuntimeException('Client should not be created for help output.');
    }
}
