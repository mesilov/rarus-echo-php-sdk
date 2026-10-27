<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Rarus\Echo\Infrastructure\Console\Diagnostics\CheckResult;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConfigurationCheck;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConnectionCheck;
use Rarus\Echo\Infrastructure\Console\Diagnostics\FfmpegCheck;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DoctorCommand extends Command
{
    public function __construct(
        private readonly ConfigurationCheck $configuration,
        private readonly ConnectionCheck $connection = new ConnectionCheck(),
        private readonly FfmpegCheck $ffmpeg = new FfmpegCheck()
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setName('doctor')
            ->setDescription('Check configuration, authenticated Echo access and ffmpeg readiness.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Write diagnostic report as JSON.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->hasOption('config') ? $input->getOption('config') : null;
        if ($path !== null && !is_string($path)) {
            return Command::INVALID;
        }
        $configuration = $this->configuration->run($path);
        $checks = [
            'config' => $configuration->check,
            'connection' => $this->connection->run($configuration->credentials),
            'ffmpeg' => $this->ffmpeg->run(),
        ];
        $status = $this->overallStatus($checks);
        if ($input->getOption('json')) {
            $output->writeln(json_encode([
                'schema_version' => 1,
                'status' => $status,
                'checks' => array_map(static fn (CheckResult $check): array => $check->toArray(), $checks),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);
        } else {
            foreach ($checks as $name => $check) {
                $output->writeln(sprintf('%s: %s - %s', $name, strtoupper($check->status), $check->message), OutputInterface::OUTPUT_RAW);
                foreach ($check->details as $key => $value) {
                    $output->writeln(sprintf('  %s: %s', $key, $value), OutputInterface::OUTPUT_RAW);
                }
            }
            $output->writeln('overall: ' . strtoupper($status), OutputInterface::OUTPUT_RAW);
        }

        return $status === 'error' ? Command::FAILURE : Command::SUCCESS;
    }

    /** @param array<string, CheckResult> $checks */
    private function overallStatus(array $checks): string
    {
        $status = 'ok';
        foreach ($checks as $check) {
            if (in_array($check->status, ['error', 'skipped'], true)) {
                return 'error';
            }
            if ($check->status === 'warning') {
                $status = 'warning';
            }
        }

        return $status;
    }
}
