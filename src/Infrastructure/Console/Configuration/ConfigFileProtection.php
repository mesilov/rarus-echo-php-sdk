<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

/**
 * @internal Windows ACL strategy adapted from bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00.
 * Original license: THIRD-PARTY-NOTICES.txt in this directory.
 */
class ConfigFileProtection
{
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
        $process = @proc_open(
            [$systemRoot . '/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), 'RARUS_ECHO_ACL_PATH' => $path, 'RARUS_ECHO_ACL_CREATE' => $initialize ? '1' : '0']
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot establish private Windows configuration storage.');
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new \RuntimeException('Cannot establish or verify current-user Windows configuration permissions.');
        }
    }
}
