<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Diagnostics;

use Rarus\Echo\Core\Credentials;

/** @internal Credentials are passed to the connection check, never included in output. */
final readonly class ConfigurationDiagnosis
{
    public function __construct(public CheckResult $check, public ?Credentials $credentials = null)
    {
    }
}
