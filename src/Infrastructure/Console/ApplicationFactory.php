<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

use Rarus\Echo\Contracts\EchoClientFactoryInterface;
use Rarus\Echo\Infrastructure\Console\Command\QueueCommand;
use Rarus\Echo\Infrastructure\Console\Command\StatusCommand;
use Rarus\Echo\Infrastructure\Console\Command\SubmitCommand;
use Rarus\Echo\Infrastructure\Console\Command\TranscriptCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

final class ApplicationFactory
{
    public static function create(?EchoClientFactoryInterface $clientFactory = null): Application
    {
        $clientFactory ??= new EnvironmentEchoClientFactory((string) getcwd());

        $application = new Application('RARUS Echo CLI');
        self::addCommand($application, new QueueCommand($clientFactory));
        self::addCommand($application, new StatusCommand($clientFactory));
        self::addCommand($application, new TranscriptCommand($clientFactory));
        self::addCommand($application, new SubmitCommand($clientFactory));

        return $application;
    }

    private static function addCommand(Application $application, Command $command): void
    {
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);

            return;
        }

        if (method_exists($application, 'add')) {
            (new \ReflectionMethod($application, 'add'))->invoke($application, $command);

            return;
        }

        throw new \LogicException('Unsupported Symfony Console Application version.');
    }
}
