<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Rarus\Echo\Contracts\EchoClientFactoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

abstract class AbstractEchoCommand extends Command
{
    public function __construct(protected readonly EchoClientFactoryInterface $clientFactory)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Write command result as JSON.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function writeJson(OutputInterface $output, array $payload): void
    {
        $output->writeln(
            (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            OutputInterface::OUTPUT_RAW
        );
    }

    protected function parseFileId(InputInterface $input, OutputInterface $output): ?Uuid
    {
        $fileId = (string) $input->getArgument('file-id');

        try {
            return Uuid::fromString($fileId);
        } catch (\Throwable) {
            $this->errorOutput($output)->writeln('Error: file-id must be a valid UUID.');

            return null;
        }
    }

    protected function writeError(OutputInterface $output, \Throwable $exception): int
    {
        $this->errorOutput($output)->writeln(sprintf('Error: %s', $exception->getMessage()));

        return Command::FAILURE;
    }

    protected function wantsJson(InputInterface $input): bool
    {
        return (bool) $input->getOption('json');
    }

    protected function errorOutput(OutputInterface $output): OutputInterface
    {
        if ($output instanceof ConsoleOutputInterface) {
            return $output->getErrorOutput();
        }

        return $output;
    }
}
