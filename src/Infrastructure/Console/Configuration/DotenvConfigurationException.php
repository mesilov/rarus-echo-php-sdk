<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

/** @internal Identifies an environment-loading failure without retaining secret parser output. */
final class DotenvConfigurationException extends \RuntimeException
{
}
