<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

/**
 * @internal Windows ACL strategy adapted from bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00.
 * Original license: THIRD-PARTY-NOTICES.txt in this directory.
 */
class ConfigFileProtection
{
    public function __construct(private readonly float $timeoutSeconds = 10.0)
    {
        if (!is_finite($timeoutSeconds) || $timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('ACL subprocess timeout must be positive and finite.');
        }
    }

    public function protect(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->windows($path, true);

            return;
        }
        if (!@chmod($path, is_dir($path) ? 0o700 : 0o600)) {
            throw new \RuntimeException('Cannot protect configuration storage.');
        }
        $this->check($path);
    }

    public function check(string $path): void
    {
        if (is_link($path)) {
            throw new \RuntimeException('Configuration storage must not be a symbolic link.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $this->windows($path, false);

            return;
        }
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0o077) !== 0
            || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())
        ) {
            throw new \RuntimeException('Configuration must be private to its owner (file 0600, directory 0700).');
        }
    }

    private function windows(string $path, bool $initialize): void
    {
        $script = <<<'PS_WRAP'
            $ErrorActionPreference = 'Stop'
            $PSModuleAutoLoadingPreference = 'None'
            $path = $env:RARUS_ECHO_ACL_PATH
            $owner = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
            $directory = [System.IO.Directory]::Exists($path)
            if ($env:RARUS_ECHO_ACL_CREATE -eq '1') {
                if ($directory) {
                    $security = [System.Security.AccessControl.DirectorySecurity]::new()
                    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($owner, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
                } else {
                    $security = [System.Security.AccessControl.FileSecurity]::new()
                    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($owner, 'FullControl', 'Allow')
                }
                $security.SetOwner($owner)
                $security.SetAccessRuleProtection($true, $false)
                $security.AddAccessRule($rule)
                if ($directory) { [System.IO.Directory]::SetAccessControl($path, $security) }
                else { [System.IO.File]::SetAccessControl($path, $security) }
            }
            if ($directory) { $actual = [System.IO.Directory]::GetAccessControl($path) }
            else { $actual = [System.IO.File]::GetAccessControl($path) }
            if (!$actual.AreAccessRulesProtected -or $actual.GetOwner([System.Security.Principal.SecurityIdentifier]).Value -ne $owner.Value) { exit 1 }
            $rules = $actual.GetAccessRules($true, $true, [System.Security.Principal.SecurityIdentifier])
            if ($rules.Count -eq 0) { exit 1 }
            foreach ($rule in $rules) {
                if ($rule.IdentityReference.Value -ne $owner.Value -or $rule.AccessControlType -ne 'Allow' -or ($rule.FileSystemRights -band [System.Security.AccessControl.FileSystemRights]::FullControl) -ne [System.Security.AccessControl.FileSystemRights]::FullControl) { exit 1 }
            }
            PS_WRAP;
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT');
        if (!$systemRoot) {
            throw new \RuntimeException('Cannot locate Windows configuration protection tools.');
        }
        $environment = getenv();
        foreach (array_keys($environment) as $key) {
            if (str_starts_with(strtoupper($key), 'RARUS_ECHO_')) {
                unset($environment[$key]);
            }
        }
        $environment['RARUS_ECHO_ACL_PATH'] = $path;
        $environment['RARUS_ECHO_ACL_CREATE'] = $initialize ? '1' : '0';
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = @proc_open(
            [$systemRoot . '/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            null,
            $environment,
            ['bypass_shell' => true, 'create_process_group' => true]
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot establish private Windows configuration storage.');
        }

        try {
            $deadline = microtime(true) + $this->timeoutSeconds;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Windows ACL subprocess timeout exceeded.');
                }
                usleep(10000);
            } while (true);
            if ($status['exitcode'] !== 0) {
                throw new \RuntimeException('Cannot establish or verify current-user Windows configuration permissions.');
            }
        } finally {
            $this->closeProcess($process);
        }
    }

    /**
     * The child may exit between polls without any PHP resource mutation.
     *
     * @param resource $process
     * @phpstan-impure
     */
    private function processIsRunning($process): bool
    {
        return proc_get_status($process)['running'];
    }

    /** @param resource $process */
    private function closeProcess($process): void
    {
        if ($this->processIsRunning($process)) {
            @proc_terminate($process);
            $deadline = microtime(true) + 0.2;
            while ($this->processIsRunning($process) && microtime(true) < $deadline) {
                usleep(10000);
            }
            if ($this->processIsRunning($process)) {
                @proc_terminate($process, 9);
            }
        }
        proc_close($process);
    }
}
