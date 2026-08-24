<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli\Contract;

interface EchoClientFactoryInterface
{
    public function create(): EchoClientInterface;
}
