<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Rarus\Echo\Infrastructure\Console\Media\AudioExtractor;
use Rarus\Echo\Infrastructure\Console\Media\MediaCancellationException;
use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExtractAudioCommand extends Command implements SignalableCommandInterface
{
    private ?OutputInterface $errorOutput = null;

    public function __construct(private readonly AudioExtractor $extractor = new AudioExtractor())
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setName('extract-audio')
            ->setDescription('Extract audio from one video into a sibling file with the same basename, without uploading.')
            ->addArgument('file', InputArgument::REQUIRED, 'Local video path.')
            ->addOption('audio-stream', null, InputOption::VALUE_REQUIRED, 'Absolute non-negative audio stream index.')
            ->addOption('media-timeout', null, InputOption::VALUE_REQUIRED, 'Combined probe and preparation deadline in seconds.', '600')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Write command result as JSON.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $windowsHandler = PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_set_ctrl_handler');
        $handler = function (int $event): void {
            $this->handleSignal($event === 0 ? 2 : 15);
        };
        if ($windowsHandler) {
            sapi_windows_set_ctrl_handler($handler);
        }

        try {
            $options = MediaOptions::parse($input->getOption('audio-stream'), false, $input->getOption('media-timeout'), 1);
            $path = $this->extractor->extract((string) $input->getArgument('file'), $options, function (string $message): void {
                $this->errorOutput?->writeln($message, OutputInterface::OUTPUT_RAW);
            });
            $output->writeln(
                $input->getOption('json') ? json_encode(['output' => $path], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : $path,
                OutputInterface::OUTPUT_RAW
            );

            return Command::SUCCESS;
        } catch (MediaCancellationException $exception) {
            return $exception->exitCode;
        } catch (\Throwable $exception) {
            $this->errorOutput->writeln('Error: ' . $exception->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        } finally {
            if ($windowsHandler) {
                sapi_windows_set_ctrl_handler($handler, false);
            }
            $this->errorOutput = null;
        }
    }

    /** @return list<int> */
    #[\Override]
    public function getSubscribedSignals(): array
    {
        if (!function_exists('pcntl_signal')) {
            return [];
        }
        $signals = [];
        foreach (['SIGINT', 'SIGTERM'] as $name) {
            if (defined($name)) {
                $signals[] = (int) constant($name);
            }
        }

        return $signals;
    }

    #[\Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if (!$this->errorOutput instanceof OutputInterface) {
            return 128 + $signal;
        }
        $name = match ($signal) {
            2 => 'SIGINT',
            15 => 'SIGTERM',
            default => (string) $signal,
        };
        $this->errorOutput->writeln(sprintf('Signal %s received, shutting down.', $name));
        $this->extractor->cancel();

        throw new MediaCancellationException(128 + $signal);
    }
}
