<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli\Command;

use Rarus\Echo\Enum\TaskType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

final class TranscriptCommand extends AbstractEchoCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('transcript')
            ->setDescription('Show transcription result for one file.')
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
            $transcript = $this->clientFactory->create()->getTranscript($fileId);
        } catch (\Throwable $exception) {
            return $this->writeError($output, $exception);
        }

        if ($this->wantsJson($input)) {
            $this->writeJson($output, [
                'file_id' => $transcript->fileId->toRfc4122(),
                'status' => $transcript->transcriptionStatus->value,
                'task_type' => $transcript->taskType?->value,
                'result' => $transcript->result,
            ]);

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('file_id: %s', $transcript->fileId->toRfc4122()));
        $output->writeln(sprintf('status: %s', $transcript->transcriptionStatus->value));
        if ($transcript->taskType instanceof TaskType) {
            $output->writeln(sprintf('task_type: %s', $transcript->taskType->value));
        }
        $output->writeln('result:');
        if ($transcript->result !== null) {
            $output->writeln($transcript->result);
        }

        return Command::SUCCESS;
    }
}
