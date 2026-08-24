<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli\Support;

use Rarus\Echo\Cli\Contract\EchoClientFactoryInterface;
use Rarus\Echo\Cli\Contract\EchoClientInterface;

final class FakeEchoClientFactory implements EchoClientFactoryInterface
{
    public int $calls = 0;

    public function __construct(private readonly EchoClientInterface $client)
    {
    }

    #[\Override]
    public function create(): EchoClientInterface
    {
        ++$this->calls;

        return $this->client;
    }
}
