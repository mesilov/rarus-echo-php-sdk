<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

final class StatusCommand extends AbstractEchoCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('status')
            ->setDescription('Show transcription status for one file.')
            ->addArgument('file-id', InputArgument::REQUIRED, 'RARUS Echo file UUID.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileId = $this->parseFileId($input, $output);
        if (!$fileId instanceof Uuid) {
            return Command::FAILURE;
        }

        try {
            $status = $this->clientFactory->create()->getStatus($fileId);
        } catch (\Throwable $exception) {
            return $this->writeError($output, $exception);
        }

        if ($this->wantsJson($input)) {
            $this->writeJson($output, [
                'file_id' => $status->fileId->toRfc4122(),
                'status' => $status->transcriptionStatus->value,
                'file_size' => $status->fileSize,
                'file_duration' => $status->fileDuration,
                'timestamp_arrival' => $status->timestampArrival->format(DATE_ATOM),
            ]);

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('file_id: %s', $status->fileId->toRfc4122()));
        $output->writeln(sprintf('status: %s', $status->transcriptionStatus->value));
        $output->writeln(sprintf('file_size: %d', $status->fileSize));
        $output->writeln(sprintf('file_duration: %d', $status->fileDuration));
        $output->writeln(sprintf('timestamp_arrival: %s', $status->timestampArrival->format(DATE_ATOM)));

        return Command::SUCCESS;
    }
}
