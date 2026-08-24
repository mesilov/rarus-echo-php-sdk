<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class QueueCommand extends AbstractEchoCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('queue')
            ->setDescription('Show aggregated transcription queue information.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $queueInfo = $this->clientFactory->create()->getQueueInfo();
        } catch (\Throwable $exception) {
            return $this->writeError($output, $exception);
        }

        if ($this->wantsJson($input)) {
            $this->writeJson($output, [
                'files_count' => $queueInfo->filesCount,
                'files_size' => $queueInfo->filesSize,
                'files_duration' => $queueInfo->filesDuration,
            ]);

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('files_count: %d', $queueInfo->filesCount));
        $output->writeln(sprintf('files_size: %d MB', $queueInfo->filesSize));
        $output->writeln(sprintf('files_duration: %d minutes', $queueInfo->filesDuration));

        return Command::SUCCESS;
    }
}
