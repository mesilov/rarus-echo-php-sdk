<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\ApplicationFactory;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FailingEchoClientFactory;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationFactoryTest extends TestCase
{
    public function testCreatesApplicationWithServiceCommands(): void
    {
        $application = ApplicationFactory::create(new FakeEchoClientFactory(new FakeEchoClient()));

        $this->assertTrue($application->has('queue'));
        $this->assertTrue($application->has('status'));
        $this->assertTrue($application->has('transcript'));
        $this->assertTrue($application->has('submit'));
    }

    public function testTopLevelHelpDoesNotCreateClient(): void
    {
        $factory = new FailingEchoClientFactory();
        $application = ApplicationFactory::create($factory);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $exitCode = $tester->run(['--help' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('List commands', $tester->getDisplay());
        $this->assertSame(0, $factory->calls);
    }

    public function testBinEntrypointUsesComposerProxyAutoloadPath(): void
    {
        $root = dirname(__DIR__, 4);
        $tempDir = sys_get_temp_dir() . '/rarus-echo-bin-test-' . bin2hex(random_bytes(4));

        self::assertTrue(mkdir($tempDir));

        $autoloadPath = $tempDir . '/autoload.php';
        $prependPath = $tempDir . '/prepend.php';

        file_put_contents(
            $autoloadPath,
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace Rarus\Echo\Infrastructure\Console;

                final class ApplicationFactory
                {
                    public static function create(): object
                    {
                        return new class {
                            public function run(): int
                            {
                                fwrite(STDOUT, 'proxy-autoload');

                                return 17;
                            }
                        };
                    }
                }
                PHP
        );
        file_put_contents(
            $prependPath,
            sprintf(
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    $GLOBALS['_composer_autoload_path'] = %s;
                    PHP,
                var_export($autoloadPath, true)
            )
        );

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-d', 'auto_prepend_file=' . $prependPath, $root . '/bin/rarus-echo'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root
        );

        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        self::assertIsString($stdout);
        self::assertIsString($stderr);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertSame(17, $exitCode, $stderr);
        self::assertSame('proxy-autoload', $stdout);
    }
}
