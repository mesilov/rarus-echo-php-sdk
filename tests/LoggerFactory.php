<?php

/**
 * This file is part of the rarus-echo-php-sdk package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Rarus\Echo\Tests;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function defaultStdout(): LoggerInterface
    {
        $logger = new Logger('echo-test');
        $logger->pushHandler(new StreamHandler('php://stdout'));

        return $logger;
    }
}
