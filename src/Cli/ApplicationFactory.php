<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli;

use Rarus\Echo\Cli\Command\QueueCommand;
use Rarus\Echo\Cli\Command\StatusCommand;
use Rarus\Echo\Cli\Command\SubmitCommand;
use Rarus\Echo\Cli\Command\TranscriptCommand;
use Rarus\Echo\Cli\Contract\EchoClientFactoryInterface;
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
