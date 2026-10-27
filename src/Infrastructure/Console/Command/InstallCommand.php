<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Command;

use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfig;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

final class InstallCommand extends Command
{
    public function __construct(
        private readonly ConnectionConfigStore $store = new ConnectionConfigStore(),
        private readonly ?ConfigPathResolver $paths = null
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setName('install')->setDescription('Configure and save a private RARUS Echo connection interactively.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        if (!$input->isInteractive()) {
            $errors->writeln('Error: install requires interactive input. Use environment credentials for unattended runs.');

            return Command::INVALID;
        }

        try {
            $option = $input->hasOption('config') ? $input->getOption('config') : null;
            if ($option !== null && !is_string($option)) {
                throw new \InvalidArgumentException('--config requires a file path.');
            }
            $path = ($this->paths ?? new ConfigPathResolver((string) getcwd()))->resolve($option);
            $helper = $this->getHelper('question');
            if (!$helper instanceof QuestionHelper) {
                throw new \LogicException('Question helper is unavailable.');
            }
            $exists = file_exists($path) || is_link($path);
            if ($exists && !$helper->ask($input, $output, new ConfirmationQuestion('Configuration exists. Replace it? [y/N] ', false))) {
                $output->writeln('Configuration unchanged.');

                return Command::SUCCESS;
            }
            $api = new Question('API key: ');
            $api->setHidden(true)->setHiddenFallback(false)->setMaxAttempts(3);
            $api->setValidator(static function (mixed $value): string {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException('API key must be a UUID.');
                }

                return ConnectionConfig::validateUuid($value, 'api_key');
            });
            $user = new Question('User ID: ');
            $user->setMaxAttempts(3)->setValidator(static function (mixed $value): string {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException('User ID must be a UUID.');
                }

                return ConnectionConfig::validateUuid($value, 'user_id');
            });
            $url = new Question('Service URL [' . ConnectionConfig::DEFAULT_BASE_URL . ']: ', ConnectionConfig::DEFAULT_BASE_URL);
            $url->setMaxAttempts(3)->setValidator(static function (mixed $value): string {
                if (!is_string($value)) {
                    throw new \InvalidArgumentException('Service URL is required.');
                }

                return ConnectionConfig::validateUrl($value);
            });
            $apiKey = $helper->ask($input, $output, $api);
            $userId = $helper->ask($input, $output, $user);
            $baseUrl = $helper->ask($input, $output, $url);
            if (!is_string($apiKey) || !is_string($userId) || !is_string($baseUrl)) {
                throw new \RuntimeException('Setup input was not completed.');
            }
            $this->store->save($path, ConnectionConfig::fromValues($apiKey, $userId, $baseUrl), $exists);
            $output->writeln('Configuration saved to: ' . $path, OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        } catch (\Throwable $error) {
            $errors->writeln('Error: ' . $error->getMessage(), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
    }
}
