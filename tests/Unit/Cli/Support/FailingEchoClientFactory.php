<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli\Support;

use Rarus\Echo\Cli\Contract\EchoClientFactoryInterface;
use Rarus\Echo\Cli\Contract\EchoClientInterface;

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
