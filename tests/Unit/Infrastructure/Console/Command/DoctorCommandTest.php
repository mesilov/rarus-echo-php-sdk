<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Command\DoctorCommand;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfig;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConfigurationCheck;
use Rarus\Echo\Infrastructure\Console\Diagnostics\ConnectionCheck;
use Rarus\Echo\Infrastructure\Console\Diagnostics\FfmpegCheck;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class DoctorCommandTest extends TestCase
{
    private string $directory;
    /** @var array<string, array{mixed, mixed}> */
    private array $previous = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-doctor-command-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        foreach (['RARUS_ECHO_API_KEY', 'RARUS_ECHO_USER_ID', 'RARUS_ECHO_BASE_URL'] as $key) {
            $this->previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->previous as $key => [$env, $server]) {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($env !== null) {
                $_ENV[$key] = $env;
            }
            if ($server !== null) {
                $_SERVER[$key] = $server;
            }
        }
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function tester(bool $ffmpegAvailable = false, bool $serverAvailable = true): ApplicationTester
    {
        $finder = $this->createMock(ExecutableFinder::class);
        $finder->method('find')->willReturn($ffmpegAvailable ? PHP_BINARY : null);
        $ffmpeg = new FfmpegCheck($finder, static fn (string $path): Process => new Process([$path, '-r', 'echo "ffmpeg version 7.1.1 Copyright fixture";']));
        $paths = new ConfigPathResolver($this->directory, ['HOME' => $this->directory], 'Linux');
        $config = new ConfigurationCheck(new ConnectionConfigResolver($this->directory, paths: $paths), $paths);
        $http = new MockHttpClient($serverAvailable
            ? new MockResponse('{"results":[{"files_count":0,"files_size":0,"files_duration":0}]}')
            : static fn (): never => throw new \LogicException('Network must be skipped'));
        $application = new Application();
        $application->setAutoExit(false);
        $application->getDefinition()->addOption(new InputOption('config', null, InputOption::VALUE_REQUIRED));
        // add() remains available on supported Symfony 6/7 versions; match the production compatibility adapter.
        $command = new DoctorCommand($config, new ConnectionCheck($http), $ffmpeg);
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            (new \ReflectionMethod($application, 'add'))->invoke($application, $command);
        }

        return new ApplicationTester($application);
    }

    private function saveConfig(): string
    {
        $path = $this->directory . '/config.json';
        (new ConnectionConfigStore())->save($path, ConnectionConfig::fromValues('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222'));

        return $path;
    }

    public function testArgumentFreeInvocationReportsAllChecksAndOverallFailure(): void
    {
        $tester = $this->tester(serverAvailable: false);
        self::assertSame(1, $tester->run(['command' => 'doctor']));
        $output = $tester->getDisplay();
        self::assertStringContainsString('config: ERROR', $output);
        self::assertStringContainsString('connection: SKIPPED', $output);
        self::assertStringContainsString('ffmpeg: ERROR', $output);
        self::assertStringContainsString('overall: ERROR', $output);
    }

    public function testSuccessfulJsonReportIsCompleteAndDoesNotExposeCredentials(): void
    {
        $path = $this->saveConfig();
        $before = file_get_contents($path);
        $tester = $this->tester(ffmpegAvailable: true);
        self::assertSame(0, $tester->run(['command' => 'doctor', '--config' => $path, '--json' => true]));
        $json = $tester->getDisplay();
        $report = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $report['schema_version']);
        self::assertSame('ok', $report['status']);
        self::assertSame(['config', 'connection', 'ffmpeg'], array_keys($report['checks']));
        foreach ($report['checks'] as $check) {
            self::assertSame(['status', 'code', 'message', 'details'], array_keys($check));
            self::assertSame('ok', $check['status']);
        }
        self::assertStringNotContainsString('11111111', $json);
        self::assertStringNotContainsString('22222222', $json);
        self::assertSame($before, file_get_contents($path));
    }

    public function testUnusedMissingConfigOnlyWarnsWithValidEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '11111111-1111-4111-8111-111111111111';
        $_ENV['RARUS_ECHO_USER_ID'] = '22222222-2222-4222-8222-222222222222';
        $tester = $this->tester(ffmpegAvailable: true);
        self::assertSame(0, $tester->run(['command' => 'doctor', '--json' => true]));
        $report = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('warning', $report['status']);
        self::assertSame('environment', $report['checks']['config']['details']['source']);
        self::assertSame('ok', $report['checks']['connection']['status']);
    }

    public function testJsonFailureStillContainsEveryCheck(): void
    {
        $tester = $this->tester(serverAvailable: false);
        self::assertSame(1, $tester->run(['command' => 'doctor', '--config' => $this->directory . '/missing.json', '--json' => true]));
        $report = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $report['status']);
        self::assertSame('connection_skipped', $report['checks']['connection']['code']);
        self::assertSame('error', $report['checks']['ffmpeg']['status']);
    }

    public function testHelpDoesNotProbeNetworkOrDependencies(): void
    {
        $tester = $this->tester(serverAvailable: false);
        self::assertSame(0, $tester->run(['command' => 'doctor', '--help' => true, '--config' => '/missing.json']));
        self::assertStringContainsString('--json', $tester->getDisplay());
    }
}
