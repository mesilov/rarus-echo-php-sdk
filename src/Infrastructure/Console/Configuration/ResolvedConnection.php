<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

use Rarus\Echo\Core\Credentials;

/** @internal Complete connection and its selected source; never serialize credentials. */
final readonly class ResolvedConnection
{
    public function __construct(public Credentials $credentials, public string $source)
    {
    }
}
