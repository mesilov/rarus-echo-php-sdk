<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console;

use Rarus\Echo\Contracts\EchoClientInterface;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigResolver;
use Rarus\Echo\Services\ServiceFactory;

final readonly class EnvironmentEchoClientFactory implements ConfigurableEchoClientFactoryInterface
{
    public function __construct(private string $workingDirectory)
    {
    }

    #[\Override]
    public function create(): EchoClientInterface
    {
        return $this->createWithConfig(null);
    }

    #[\Override]
    public function createWithConfig(?string $path): EchoClientInterface
    {
        $credentials = (new ConnectionConfigResolver($this->workingDirectory))->resolve($path);

        return new SdkEchoClient(new ServiceFactory($credentials));
    }
}
