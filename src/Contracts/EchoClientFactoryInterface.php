<?php

declare(strict_types=1);

namespace Rarus\Echo\Contracts;

interface EchoClientFactoryInterface
{
    public function create(): EchoClientInterface;
}
