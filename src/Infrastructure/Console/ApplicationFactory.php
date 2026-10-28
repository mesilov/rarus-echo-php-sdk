<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

use Rarus\Echo\Contracts\EchoClientFactoryInterface;
use Rarus\Echo\Infrastructure\Console\Command\DoctorCommand;
use Rarus\Echo\Infrastructure\Console\Command\ExtractAudioCommand;
use Rarus\Echo\Infrastructure\Console\Command\InstallCommand;
use Rarus\Echo\Infrastructure\Console\Command\QueueCommand;
use Rarus\Echo\Infrastructure\Console\Command\StatusCommand;
use Rarus\Echo\Infrastructure\Console\Command\SubmitCommand;
use Rarus\Echo\Infrastructure\Console\Command\TranscriptCommand;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigResolver;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConfigurationCheck;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

final class ApplicationFactory
{
    public static function create(?EchoClientFactoryInterface $clientFactory = null): Application
    {
        $clientFactory ??= new EnvironmentEchoClientFactory((string) getcwd());

        $application = new Application('RARUS Echo CLI', BuildVersion::get());
        $application->getDefinition()->addOption(new InputOption('config', null, InputOption::VALUE_REQUIRED, 'Read connection settings from this file.'));
        self::addCommand($application, new InstallCommand());
        self::addCommand($application, new ExtractAudioCommand());
        $workingDirectory = (string) getcwd();
        self::addCommand($application, new DoctorCommand(new ConfigurationCheck(
            new ConnectionConfigResolver($workingDirectory),
            new ConfigPathResolver($workingDirectory)
        )));
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
