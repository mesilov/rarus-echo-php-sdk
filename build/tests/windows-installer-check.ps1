# Offline contract checks. Windows PowerShell 5.1 runs the native executable tests.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0
$installer = Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'install.ps1'
if (-not (Test-Path -LiteralPath $installer -PathType Leaf)) {
    throw 'Missing install.ps1: the Windows installer has not been implemented.'
}
$parseErrors = $null
$tokens = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile($installer, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count -ne 0) { throw ($parseErrors | Out-String) }
# Load the real functions without running the top-level entry point. Only transport
# and platform discovery are replaced below; hashing, ZIP handling and execution are real.
foreach ($definition in $ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $false)) {
    . ([scriptblock]::Create($definition.Extent.Text))
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$root = Join-Path ([IO.Path]::GetTempPath()) ('rarus-echo-installer-check-' + [Guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($root)
$script:checks = 0
$script:downloads = New-Object 'System.Collections.Generic.List[string]'
$script:sha = 'a' * 40
$script:tag = 'v1.2.3'
$script:manifestOverride = $null
$script:missingAsset = $false
$script:asset = $null
$script:isNativeWindows = [Environment]::OSVersion.Platform -eq [PlatformID]::Win32NT
$originalLocal = $env:LOCALAPPDATA
$originalRoaming = $env:APPDATA
$originalPath = [Environment]::GetEnvironmentVariable('PATH', 'User')

function Assert-True([bool]$Value, [string]$Message) {
    if (-not $Value) { throw $Message }
}
function Test-Case([string]$Name, [scriptblock]$Body) {
    & $Body
    $script:checks++
    Write-Host ('PASS ' + $Name)
}
function Assert-Fails([scriptblock]$Body, [string]$Pattern) {
    $failure = $null
    try { & $Body } catch { $failure = $_.Exception.Message }
    Assert-True ($null -ne $failure) 'Expected installation/validation to fail.'
    Assert-True ($failure -match $Pattern) ('Unexpected failure: ' + $failure)
}
function New-TestArchive([string]$Path, [string]$Executable, [object[]]$Extra = @(), [string]$Omit = '') {
    $zip = [IO.Compression.ZipFile]::Open($Path, [IO.Compression.ZipArchiveMode]::Create)
    try {
        $names = @('rarus-echo.exe', 'build-info.json', 'runtime-sources.json', 'THIRD-PARTY-NOTICES.md',
            'RARUS-ECHO-LICENSE.txt', 'BITRIX24-CLI-LICENSE.txt', 'YIIPRESS-LICENSE.txt', 'runtime-licenses/PHP.txt')
        foreach ($name in $names) {
            if ($name -eq $Omit) { continue }
            $entry = $zip.CreateEntry($name)
            $stream = $entry.Open()
            try {
                if ($name -eq 'rarus-echo.exe' -and $Executable) {
                    $bytes = [IO.File]::ReadAllBytes($Executable)
                } else { $bytes = [Text.Encoding]::UTF8.GetBytes('license / metadata fixture') }
                $stream.Write($bytes, 0, $bytes.Length)
            } finally { $stream.Dispose() }
        }
        foreach ($item in $Extra) {
            $entry = $zip.CreateEntry($item.Name)
            if ($null -ne $item.Attributes) { $entry.ExternalAttributes = $item.Attributes }
            $stream = $entry.Open()
            try { $stream.WriteByte(120) } finally { $stream.Dispose() }
        }
    } finally { $zip.Dispose() }
}
function New-ConsoleFixture([string]$Path, [string]$Identity, [string]$Counter = '') {
    $class = 'Fixture' + [Guid]::NewGuid().ToString('N')
    $identityLiteral = $Identity.Replace('\', '\\').Replace('"', '\"')
    $counterLiteral = $Counter.Replace('\', '\\').Replace('"', '\"')
    $counterCode = ''
    if ($Counter) {
        $counterCode = 'if (System.IO.File.Exists("' + $counterLiteral + '")) { Console.WriteLine("wrong staged identity"); return 0; } System.IO.File.WriteAllText("' + $counterLiteral + '", "seen");'
    }
    $source = 'using System; public class ' + $class + ' { public static int Main(string[] args) { if(args.Length != 2 || args[0] != "--version" || args[1] != "--no-ansi") return 8; ' + $counterCode + ' Console.WriteLine("' + $identityLiteral + '"); return 0; }}'
    Add-Type -TypeDefinition $source -Language CSharp -OutputAssembly $Path -OutputType ConsoleApplication
}
function Invoke-RarusEchoDownload([string]$Uri, [string]$Destination) {
    $script:downloads.Add($Uri)
    Assert-True ($Uri.StartsWith('https://')) 'The installer requested a non-HTTPS URL.'
    if ($Uri -eq 'https://api.github.com/repos/mesilov/rarus-echo-php-sdk/releases/latest') {
        [IO.File]::WriteAllText($Destination, ('{"tag_name":"' + $script:tag + '","draft":false,"prerelease":false}'))
        return
    }
    Assert-True ($Uri.StartsWith('https://github.com/mesilov/rarus-echo-php-sdk/releases/download/' + [Uri]::EscapeDataString($script:tag) + '/')) 'Assets must use the selected immutable release tag.'
    if ($script:missingAsset) { throw 'HTTP 404: release asset is missing.' }
    if ($Uri.EndsWith('/SHA256SUMS')) {
        $content = $script:manifestOverride
        if ($null -eq $content) { $content = (Get-FileHash -LiteralPath $script:asset -Algorithm SHA256).Hash + '  rarus-echo-windows-amd64.zip' + "`n" }
        [IO.File]::WriteAllText($Destination, $content)
    } elseif ($Uri.EndsWith('/rarus-echo-windows-amd64.zip')) {
        [IO.File]::Copy($script:asset, $Destination)
    } else { throw ('Unexpected download: ' + $Uri) }
}

try {
    Test-Case 'unsupported platform fails before any download' {
        function Get-RarusEchoPlatform { return 'linux-arm64' }
        Assert-Fails { Install-RarusEcho -NoModifyPath } 'Windows.*amd64|unsupported'
        Assert-True ($script:downloads.Count -eq 0) 'Unsupported platform performed a download.'
    }
    Test-Case 'PATH-breaking directories are rejected before download' {
        function Get-RarusEchoPlatform { return 'windows-amd64' }
        foreach ($directory in @('C:\bad;entry', "C:\bad`nentry", "C:\bad`rentry")) {
            Assert-Fails { Install-RarusEcho -InstallDir $directory -NoModifyPath } 'single PATH entry|semicolon|control'
        }
        Assert-True ($script:downloads.Count -eq 0) 'Invalid custom directory performed a download.'
    }
    Test-Case 'relative default LOCALAPPDATA is rejected before download' {
        function Get-RarusEchoPlatform { return 'windows-amd64' }
        $savedLocal = $env:LOCALAPPDATA
        try {
            $env:LOCALAPPDATA = 'relative-default'
            Assert-Fails { Install-RarusEcho -NoModifyPath } 'absolute'
        } finally { $env:LOCALAPPDATA = $savedLocal }
        Assert-True ($script:downloads.Count -eq 0) 'Invalid default directory performed a download.'
    }
    # Archive checks are portable and run under PowerShell 7 on non-Windows hosts.
    $validZip = Join-Path $root 'valid.zip'
    New-TestArchive $validZip ''
    Test-Case 'one exact SHA256 entry is required' {
        $manifest = Join-Path $root 'SHA256SUMS'
        $hash = (Get-FileHash -LiteralPath $validZip -Algorithm SHA256).Hash
        [IO.File]::WriteAllText($manifest, ($hash + '  rarus-echo-windows-amd64.zip'))
        Assert-RarusEchoChecksum $validZip $manifest 'rarus-echo-windows-amd64.zip'
        foreach ($bad in @('', 'bad  rarus-echo-windows-amd64.zip', ('0' * 64 + '  rarus-echo-windows-amd64.zip'),
            ($hash + '  rarus-echo-windows-amd64.zip' + "`n" + $hash + '  rarus-echo-windows-amd64.zip'),
            ($hash + '  other.zip'))) {
            [IO.File]::WriteAllText($manifest, $bad)
            Assert-Fails { Assert-RarusEchoChecksum $validZip $manifest 'rarus-echo-windows-amd64.zip' } 'checksum|SHA256'
        }
    }
    Test-Case 'safe archive retains required license files' {
        $destination = Join-Path $root 'expanded'
        Expand-RarusEchoArchive $validZip $destination
        Assert-True (Test-Path -LiteralPath (Join-Path $destination 'runtime-licenses/PHP.txt')) 'Missing extracted runtime license.'
    }
    $badNames = @('../outside.txt', '/outside.txt', 'runtime-licenses/../escape.txt', 'runtime-licenses\escape.txt',
        'runtime-licenses/file:stream', 'runtime-licenses/CON', 'runtime-licenses/file.', 'runtime-licenses//empty',
        'unexpected.txt', 'rarus-echo.exe', 'RARUS-ECHO.EXE', 'runtime-licenses/PHP.txt/child')
    foreach ($name in $badNames) {
        Test-Case ('unsafe archive member ' + $name) {
            $path = Join-Path $root ([Guid]::NewGuid().ToString('N') + '.zip')
            New-TestArchive $path '' @(@{ Name = $name; Attributes = 0 })
            Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root ([Guid]::NewGuid().ToString('N'))) } 'archive|member|ZIP|payload'
        }
    }
    Test-Case 'symlink archive member is rejected' {
        $path = Join-Path $root 'link.zip'
        New-TestArchive $path '' @(@{ Name = 'runtime-licenses/link'; Attributes = -1577123840 })
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'link-out') } 'archive|member|ZIP|link'
    }
    Test-Case 'missing notices fail before extraction' {
        $path = Join-Path $root 'no-notices.zip'
        New-TestArchive $path '' @() 'THIRD-PARTY-NOTICES.md'
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'no-notices-out') } 'required|notice|archive'
    }
    Test-Case 'runtime licenses containing only a directory are rejected' {
        $path = Join-Path $root 'empty-runtime.zip'
        New-TestArchive $path '' @(@{ Name = 'runtime-licenses/'; Attributes = 16 }) 'runtime-licenses/PHP.txt'
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'empty-runtime-out') } 'runtime licenses'
    }
    Test-Case 'directory component case collisions are rejected' {
        $path = Join-Path $root 'case-folders.zip'
        New-TestArchive $path '' @(@{ Name = 'runtime-licenses/Sub/license1'; Attributes = 0 }, @{ Name = 'runtime-licenses/sub/license2'; Attributes = 0 })
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'case-folders-out') } 'collid'
    }
    if (-not $script:isNativeWindows) {
        Write-Host ('PASS ' + $script:checks + ' portable checks; native Windows executable/PATH checks require Windows PowerShell 5.1.')
        return
    }
    function Get-RarusEchoPlatform { return 'windows-amd64' }
    $env:LOCALAPPDATA = Join-Path $root 'Local AppData'
    $env:APPDATA = Join-Path $root 'Roaming AppData'
    $config = Join-Path $env:APPDATA 'rarus-echo/config.json'
    [void][IO.Directory]::CreateDirectory((Split-Path $config -Parent))
    [IO.File]::WriteAllText($config, 'sensitive-user-config-fixture')
    $fixture = Join-Path $root 'fixture.exe'
    New-ConsoleFixture $fixture ('RARUS Echo CLI ' + $script:tag + ' (source ' + $script:sha + ')')
    $script:asset = Join-Path $root 'release.zip'
    New-TestArchive $script:asset $fixture
    $defaultBin = Join-Path $env:LOCALAPPDATA 'rarus-echo/bin'
    $defaultPackage = Join-Path $env:LOCALAPPDATA 'rarus-echo/package'
    Test-Case 'latest install preserves config and no-modify PATH' {
        Install-RarusEcho -NoModifyPath
        Assert-True (Test-Path -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')) 'Default executable was not installed.'
        Assert-True (Test-Path -LiteralPath (Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) 'Package notices were not installed.'
        Assert-True ([IO.File]::ReadAllText($config) -eq 'sensitive-user-config-fixture') 'Configuration changed.'
        Assert-True ([Environment]::GetEnvironmentVariable('PATH', 'User') -eq $originalPath) 'NoModifyPath changed user PATH.'
    }
    Test-Case 'repeat pinned install adds user PATH only once' {
        $script:downloads.Clear()
        Install-RarusEcho -Version $script:tag
        Install-RarusEcho -Version $script:tag
        Assert-True (-not ($script:downloads -match '/releases/latest')) 'Pinned install queried latest.'
        $entries = @(([Environment]::GetEnvironmentVariable('PATH', 'User') -split ';') | Where-Object { $_.TrimEnd('\', '/') -ieq $defaultBin.TrimEnd('\', '/') })
        Assert-True ($entries.Count -eq 1) 'Repeated install duplicated user PATH.'
    }
    Test-Case 'custom absolute path supports spaces and Unicode' {
        $custom = Join-Path $root ('custom ' + [char]0x0416 + ' bin')
        Install-RarusEcho -Version $script:tag -InstallDir $custom -NoModifyPath
        Assert-True (Test-Path -LiteralPath (Join-Path $custom 'rarus-echo.exe')) 'Custom executable missing.'
        Assert-True (Test-Path -LiteralPath (Join-Path $custom 'package/runtime-licenses/PHP.txt')) 'Custom package notices missing.'
        Assert-Fails { Install-RarusEcho -InstallDir 'relative/bin' -NoModifyPath } 'absolute'
    }
    Test-Case 'explicit prerelease updates executable and notices' {
        $script:tag = 'v1.3.0-rc.1'
        $next = Join-Path $root 'next.exe'
        New-ConsoleFixture $next ('RARUS Echo CLI ' + $script:tag + ' (source ' + $script:sha + ')')
        $script:asset = Join-Path $root 'next.zip'
        New-TestArchive $script:asset $next
        Install-RarusEcho -Version $script:tag -NoModifyPath
        $output = & (Join-Path $defaultBin 'rarus-echo.exe') --version --no-ansi
        Assert-True ($output -ceq ('RARUS Echo CLI ' + $script:tag + ' (source ' + $script:sha + ')')) 'Updated executable has wrong identity.'
    }
    $oldExecutable = (Get-FileHash -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')).Hash
    $oldNotice = [IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md'))
    function Assert-OldInstallation {
        Assert-True ((Get-FileHash -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')).Hash -eq $oldExecutable) 'Failure replaced existing executable.'
        Assert-True ([IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) -eq $oldNotice) 'Failure replaced notices.'
        Assert-True ([IO.File]::ReadAllText($config) -eq 'sensitive-user-config-fixture') 'Failure changed configuration.'
    }
    Test-Case 'missing stable assets preserve existing install' {
        $script:missingAsset = $true
        try { Assert-Fails { Install-RarusEcho -NoModifyPath } 'asset|release|404' } finally { $script:missingAsset = $false }
        Assert-OldInstallation
    }
    Test-Case 'checksum failure preserves existing install' {
        $script:manifestOverride = 'not-a-checksum  rarus-echo-windows-amd64.zip'
        try { Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'checksum|SHA256' } finally { $script:manifestOverride = $null }
        Assert-OldInstallation
    }
    Test-Case 'wrong executable identity preserves existing install' {
        $wrong = Join-Path $root 'wrong.exe'
        New-ConsoleFixture $wrong 'unrelated CLI 1.3.0'
        $script:asset = Join-Path $root 'wrong.zip'
        New-TestArchive $script:asset $wrong
        Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'identity|version'
        Assert-OldInstallation
    }
    Test-Case 'staged executable is checked before replacement' {
        $staged = Join-Path $root 'staged.exe'
        New-ConsoleFixture $staged ('RARUS Echo CLI ' + $script:tag + ' (source ' + $script:sha + ')') (Join-Path $root 'identity-counter')
        $script:asset = Join-Path $root 'staged.zip'
        New-TestArchive $script:asset $staged
        Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'identity|version'
        Assert-OldInstallation
    }
    Test-Case 'locked existing executable preserves executable and notices' {
        $script:asset = Join-Path $root 'next.zip'
        $locked = [IO.File]::Open((Join-Path $defaultBin 'rarus-echo.exe'), [IO.FileMode]::Open, [IO.FileAccess]::Read, [IO.FileShare]::None)
        try { Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'install|access|process|used|denied' } finally { $locked.Dispose() }
        Assert-OldInstallation
    }
    Test-Case 'commit failure after executable backup rolls back old executable' {
        Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;
public static class RarusEchoDirectoryLock {
    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    public static extern SafeFileHandle CreateFile(string path, uint access, uint share, IntPtr security, uint creation, uint flags, IntPtr template);
}
'@
        $lockedDirectory = [RarusEchoDirectoryLock]::CreateFile($defaultPackage, 0x80000000, 3, [IntPtr]::Zero, 3, 0x02000000, [IntPtr]::Zero)
        Assert-True (-not $lockedDirectory.IsInvalid) 'Could not lock package directory for rollback check.'
        try { Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'install|access|process|used|denied' } finally { $lockedDirectory.Dispose() }
        Assert-OldInstallation
    }
    Write-Host ('PASS ' + $script:checks + ' Windows installer checks.')
} finally {
    if ($script:isNativeWindows) { [Environment]::SetEnvironmentVariable('PATH', $originalPath, 'User') }
    $env:LOCALAPPDATA = $originalLocal
    $env:APPDATA = $originalRoaming
    if (Test-Path -LiteralPath $root) { Remove-Item -LiteralPath $root -Recurse -Force }
}
