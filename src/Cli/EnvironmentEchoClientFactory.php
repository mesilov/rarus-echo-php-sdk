<?php

declare(strict_types=1);

namespace Rarus\Echo\Cli;

use Rarus\Echo\Cli\Contract\EchoClientFactoryInterface;
use Rarus\Echo\Cli\Contract\EchoClientInterface;
use Rarus\Echo\Services\ServiceFactory;
use Symfony\Component\Dotenv\Dotenv;

final readonly class EnvironmentEchoClientFactory implements EchoClientFactoryInterface
{
    public function __construct(private string $workingDirectory)
    {
    }

    #[\Override]
    public function create(): EchoClientInterface
    {
        $this->loadDotEnv();

        return new SdkEchoClient(ServiceFactory::fromEnvironment());
    }

    private function loadDotEnv(): void
    {
        $envPath = $this->workingDirectory . '/.env';

        if (!is_file($envPath)) {
            return;
        }

        (new Dotenv())->usePutenv()->loadEnv($envPath);
    }
}
