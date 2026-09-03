<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Rarus\Echo\Contracts\EchoClientFactoryInterface;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Infrastructure\Console\SubmitWaitOptions;
use Rarus\Echo\Infrastructure\Console\TranscriptPoller;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

final class SubmitCommand extends AbstractEchoCommand implements SignalableCommandInterface
{
    private ?OutputInterface $signalErrorOutput = null;

    public function __construct(
        EchoClientFactoryInterface $clientFactory,
        private readonly TranscriptPoller $poller = new TranscriptPoller(),
        private readonly Filesystem $filesystem = new Filesystem()
    ) {
        parent::__construct($clientFactory);
    }

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
            ->addOption('timestamps-extended', null, InputOption::VALUE_NONE, 'Enable extended timestamps for diarization.')
            ->addOption('no-store-file', null, InputOption::VALUE_NONE, 'Do not store submitted files after processing.')
            ->addOption('low-priority', null, InputOption::VALUE_NONE, 'Submit with low processing priority.')
            ->addOption('request-source', null, InputOption::VALUE_REQUIRED, 'Optional request source header.')
            ->addOption('wait', null, InputOption::VALUE_NONE, 'Poll until submitted transcript results reach a terminal state.')
            ->addOption('poll-interval', null, InputOption::VALUE_REQUIRED, 'Polling interval in seconds when using --wait.', '30')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Maximum wait time in seconds when using --wait.', '7200')
            ->addOption('raw-result', null, InputOption::VALUE_NONE, 'With --wait, write only the single transcript result to stdout.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'With --wait, write the single transcript result to a file.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->signalErrorOutput = $this->errorOutput($output);

        try {
            return $this->doExecute($input, $output);
        } finally {
            $this->signalErrorOutput = null;
        }
    }

    /**
     * @return list<int>
     */
    #[\Override]
    public function getSubscribedSignals(): array
    {
        if (!\function_exists('pcntl_signal')) {
            return [];
        }

        $signals = [];

        if (\defined('SIGINT')) {
            $signals[] = \SIGINT;
        }

        if (\defined('SIGTERM')) {
            $signals[] = \SIGTERM;
        }

        return $signals;
    }

    #[\Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->signalErrorOutput?->writeln(sprintf(
            'Signal %s received, shutting down.',
            $this->formatSignalName($signal)
        ));

        return 128 + $signal;
    }

    private function doExecute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $files */
        $files = array_values((array) $input->getArgument('files'));

        $waitOptions = $this->parseWaitOptions($input, $output, count($files));
        if (!$waitOptions instanceof SubmitWaitOptions) {
            return Command::FAILURE;
        }

        $options = $this->buildTranscriptionOptions($input, $output);
        if (!$options instanceof TranscriptionOptions) {
            return Command::FAILURE;
        }

        try {
            $client = $this->clientFactory->create();
            $submitResult = $client->submit($files, $options);
        } catch (\Throwable $exception) {
            return $this->writeError($output, $exception);
        }

        $submittedFileIds = $submitResult->getFileIds();
        $fileIds = array_map(
            static fn ($fileId): string => $fileId->toRfc4122(),
            $submittedFileIds
        );

        if ($waitOptions->wait) {
            $errorOutput = $this->errorOutput($output);
            foreach ($fileIds as $fileId) {
                $errorOutput->writeln(sprintf('submitted: %s', $fileId));
            }

            try {
                $results = $this->poller->wait(
                    $client,
                    $submittedFileIds,
                    $waitOptions->pollIntervalSeconds,
                    $waitOptions->timeoutSeconds,
                    static function (string $message) use ($errorOutput): void {
                        $errorOutput->writeln($message);
                    }
                );

                return $this->writeWaitResult($input, $output, $fileIds, $results, $waitOptions);
            } catch (\Throwable $exception) {
                return $this->writeError($output, $exception);
            }
        }

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

    private function formatSignalName(int $signal): string
    {
        if (\defined('SIGINT') && $signal === \SIGINT) {
            return 'SIGINT';
        }

        if (\defined('SIGTERM') && $signal === \SIGTERM) {
            return 'SIGTERM';
        }

        return sprintf('signal %d', $signal);
    }

    private function parseWaitOptions(InputInterface $input, OutputInterface $output, int $fileCount): ?SubmitWaitOptions
    {
        $wait = (bool) $input->getOption('wait');
        $rawResult = (bool) $input->getOption('raw-result');
        $outputPath = $input->getOption('output');
        $outputPath = is_string($outputPath) && $outputPath !== '' ? $outputPath : null;

        if (!$wait && $rawResult) {
            $this->errorOutput($output)->writeln('Error: --raw-result requires --wait.');

            return null;
        }

        if (!$wait && $outputPath !== null) {
            $this->errorOutput($output)->writeln('Error: --output requires --wait.');

            return null;
        }

        if ($wait && $rawResult && $fileCount !== 1) {
            $this->errorOutput($output)->writeln('Error: --raw-result supports only one submitted file.');

            return null;
        }

        if ($wait && $outputPath !== null && $fileCount !== 1) {
            $this->errorOutput($output)->writeln('Error: --output supports only one submitted file.');

            return null;
        }

        $pollIntervalSeconds = $this->parsePositiveIntegerOption($input, $output, 'poll-interval');
        if ($pollIntervalSeconds === null) {
            return null;
        }

        $timeoutSeconds = $this->parsePositiveIntegerOption($input, $output, 'timeout');
        if ($timeoutSeconds === null) {
            return null;
        }

        return new SubmitWaitOptions($wait, $pollIntervalSeconds, $timeoutSeconds, $rawResult, $outputPath);
    }

    private function parsePositiveIntegerOption(InputInterface $input, OutputInterface $output, string $optionName): ?int
    {
        $value = $input->getOption($optionName);
        $stringValue = is_scalar($value) ? (string) $value : '';

        if (!ctype_digit($stringValue) || (int) $stringValue < 1) {
            $this->errorOutput($output)->writeln(sprintf('Error: --%s must be a positive integer.', $optionName));

            return null;
        }

        return (int) $stringValue;
    }

    /**
     * @param list<string>                   $fileIds
     * @param list<FileItemTranscriptResult> $results
     */
    private function writeWaitResult(
        InputInterface $input,
        OutputInterface $output,
        array $fileIds,
        array $results,
        SubmitWaitOptions $waitOptions
    ): int {
        if ($waitOptions->outputPath !== null) {
            $this->filesystem->dumpFile($waitOptions->outputPath, $results[0]->result ?? '');

            return Command::SUCCESS;
        }

        if ($waitOptions->rawResult) {
            $output->writeln($results[0]->result ?? '', OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        if ($this->wantsJson($input)) {
            $this->writeJson($output, [
                'file_ids' => $fileIds,
                'results' => array_map(
                    static fn (FileItemTranscriptResult $result): array => [
                        'file_id' => $result->fileId->toRfc4122(),
                        'status' => $result->transcriptionStatus->value,
                        'task_type' => $result->taskType?->value,
                        'result' => $result->result,
                    ],
                    $results
                ),
            ]);

            return Command::SUCCESS;
        }

        $output->writeln('file_ids:');
        foreach ($fileIds as $fileId) {
            $output->writeln(sprintf('- %s', $fileId));
        }

        $output->writeln('results:');
        foreach ($results as $result) {
            $output->writeln(sprintf('- file_id: %s', $result->fileId->toRfc4122()));
            $output->writeln(sprintf('  status: %s', $result->transcriptionStatus->value));
            if ($result->taskType instanceof TaskType) {
                $output->writeln(sprintf('  task_type: %s', $result->taskType->value));
            }
            $output->writeln('  result:');
            if ($result->result !== null) {
                $output->writeln($result->result, OutputInterface::OUTPUT_RAW);
            }
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
            ->withTimestampsExtended((bool) $input->getOption('timestamps-extended'))
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
