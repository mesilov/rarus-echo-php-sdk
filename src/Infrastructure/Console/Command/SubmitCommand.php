<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SubmitCommand extends AbstractEchoCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('submit')
            ->setDescription('Submit one or more files for transcription.')
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'File paths to submit.')
            ->addOption('task-type', null, InputOption::VALUE_REQUIRED, 'Task type: ' . implode(', ', TaskType::values()), TaskType::TRANSCRIPTION->value)
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'Language code: ' . implode(', ', Language::values()), Language::AUTO->value)
            ->addOption('censor', null, InputOption::VALUE_NONE, 'Enable censorship.')
            ->addOption('speakers-correction', null, InputOption::VALUE_NONE, 'Enable speaker correction.')
            ->addOption('no-store-file', null, InputOption::VALUE_NONE, 'Do not store submitted files after processing.')
            ->addOption('low-priority', null, InputOption::VALUE_NONE, 'Submit with low processing priority.')
            ->addOption('request-source', null, InputOption::VALUE_REQUIRED, 'Optional request source header.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->buildTranscriptionOptions($input, $output);
        if (!$options instanceof TranscriptionOptions) {
            return Command::FAILURE;
        }

        /** @var list<string> $files */
        $files = array_values((array) $input->getArgument('files'));

        try {
            $submitResult = $this->clientFactory->create()->submit($files, $options);
        } catch (\Throwable $exception) {
            return $this->writeError($output, $exception);
        }

        $fileIds = array_map(
            static fn ($fileId): string => $fileId->toRfc4122(),
            $submitResult->getFileIds()
        );

        if ($this->wantsJson($input)) {
            $this->writeJson($output, ['file_ids' => $fileIds]);

            return Command::SUCCESS;
        }

        $output->writeln('file_ids:');
        foreach ($fileIds as $fileId) {
            $output->writeln(sprintf('- %s', $fileId));
        }

        return Command::SUCCESS;
    }

    private function buildTranscriptionOptions(InputInterface $input, OutputInterface $output): ?TranscriptionOptions
    {
        $taskType = $this->parseTaskType($input, $output);
        if (!$taskType instanceof TaskType) {
            return null;
        }

        $language = $this->parseLanguage($input, $output);
        if (!$language instanceof Language) {
            return null;
        }

        $builder = TranscriptionOptions::create()
            ->withTaskType($taskType)
            ->withLanguage($language)
            ->withCensor((bool) $input->getOption('censor'))
            ->withSpeakersCorrection((bool) $input->getOption('speakers-correction'))
            ->withStoreFile(!((bool) $input->getOption('no-store-file')))
            ->withLowPriority((bool) $input->getOption('low-priority'));

        $requestSource = $input->getOption('request-source');
        if (is_string($requestSource) && $requestSource !== '') {
            $builder->withRequestSource($requestSource);
        }

        return $builder->build();
    }

    private function parseTaskType(InputInterface $input, OutputInterface $output): ?TaskType
    {
        $value = (string) $input->getOption('task-type');

        try {
            return TaskType::from($value);
        } catch (\ValueError) {
            $this->errorOutput($output)->writeln(sprintf(
                'Error: Invalid task type "%s". Supported values: %s.',
                $value,
                implode(', ', TaskType::values())
            ));

            return null;
        }
    }

    private function parseLanguage(InputInterface $input, OutputInterface $output): ?Language
    {
        $value = (string) $input->getOption('language');

        try {
            return Language::from($value);
        } catch (\ValueError) {
            $this->errorOutput($output)->writeln(sprintf(
                'Error: Invalid language "%s". Supported values: %s.',
                $value,
                implode(', ', Language::values())
            ));

            return null;
        }
    }
}
