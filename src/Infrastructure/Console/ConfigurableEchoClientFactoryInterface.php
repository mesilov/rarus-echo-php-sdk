<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

use Rarus\Echo\Contracts\EchoClientFactoryInterface;
use Rarus\Echo\Contracts\EchoClientInterface;

/** @internal Console-only source selection; the SDK factory contract is unchanged. */
interface ConfigurableEchoClientFactoryInterface extends EchoClientFactoryInterface
{
    public function createWithConfig(?string $path): EchoClientInterface;
}
