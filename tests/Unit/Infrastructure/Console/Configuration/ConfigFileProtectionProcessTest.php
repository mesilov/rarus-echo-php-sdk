<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Configuration;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigFileProtection;
use Symfony\Component\Filesystem\Filesystem;

final class ConfigFileProtectionProcessTest extends TestCase
{
    private string $directory;

    /** @var array<string, string|false> */
    private array $previous = [];

    #[\Override]
    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('The Unix adapter verifies the subprocess boundary; native package acceptance exercises actual Windows ACLs.');
        }
        $this->directory = sys_get_temp_dir() . '/echo-acl-process-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/System32/WindowsPowerShell/v1.0', 0o700, true);
        foreach (['SystemRoot', 'RARUS_ECHO_API_KEY', 'RARUS_ECHO_USER_ID'] as $key) {
            $this->previous[$key] = getenv($key);
        }
        putenv('SystemRoot=' . $this->directory);
        putenv('RARUS_ECHO_API_KEY=fixture-api-credential');
        putenv('RARUS_ECHO_USER_ID=fixture-user-credential');
        file_put_contents($this->directory . '/target', 'fixture');
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testAclSubprocessReceivesOnlyExplicitAclValuesAndNoEchoCredentials(): void
    {
        $this->script(<<<'PHP'
            if (getenv('RARUS_ECHO_API_KEY') !== false || getenv('RARUS_ECHO_USER_ID') !== false) { exit(23); }
            if (getenv('RARUS_ECHO_ACL_CREATE') !== '1') { exit(24); }
            file_put_contents(getenv('RARUS_ECHO_ACL_PATH') . '.verified', 'protected');
            PHP);
        $this->invoke(new ConfigFileProtection(1.0));
        self::assertSame('protected', file_get_contents($this->directory . '/target.verified'));
    }

    public function testAclSubprocessIsStoppedAtItsDeadline(): void
    {
        $this->script('usleep(3000000);');
        $start = microtime(true);

        try {
            $this->invoke(new ConfigFileProtection(0.1));
            self::fail('Expected a bounded ACL subprocess timeout.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('timeout', $exception->getMessage());
            self::assertLessThan(2.0, microtime(true) - $start);
        }
    }

    private function script(string $source): void
    {
        $path = $this->directory . '/System32/WindowsPowerShell/v1.0/powershell.exe';
        file_put_contents($path, '#!' . PHP_BINARY . "\n<?php\n" . $source);
        chmod($path, 0o700);
    }

    private function invoke(ConfigFileProtection $protection): void
    {
        // Exercise the real OS subprocess adapter on Unix with an executable stand-in.
        // Actual ACL semantics are checked by the Windows native acceptance job.
        (new \ReflectionMethod(ConfigFileProtection::class, 'windows'))->invoke($protection, $this->directory . '/target', true);
    }
}
