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
$script:platformImplementation = ${function:Get-RarusEchoPlatform}
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
$originalJavaHome = $env:JAVA_HOME
$originalPath = $null

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
function Get-TestUserPathState {
    $state = [pscustomobject]@{ Exists = $false; Value = $null; Kind = [Microsoft.Win32.RegistryValueKind]::String }
    $key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey('Environment')
    if ($null -ne $key) {
        try {
            if ($key.GetValueNames() -contains 'Path') {
                $state.Exists = $true
                $state.Value = $key.GetValue('Path', $null, [Microsoft.Win32.RegistryValueOptions]::DoNotExpandEnvironmentNames)
                $state.Kind = $key.GetValueKind('Path')
            }
        } finally { $key.Dispose() }
    }
    return $state
}
function Set-TestUserPathState([object]$State) {
    $key = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey('Environment')
    try {
        if ($State.Exists) { $key.SetValue('Path', $State.Value, $State.Kind) }
        else { $key.DeleteValue('Path', $false) }
    } finally { $key.Dispose() }
}
function Assert-TestUserPathState([object]$Expected, [string]$Message) {
    $actual = Get-TestUserPathState
    Assert-True ($actual.Exists -eq $Expected.Exists) ($Message + ': registry value existence changed.')
    if ($Expected.Exists) {
        Assert-True ($actual.Kind -eq $Expected.Kind) ($Message + ': registry value kind changed.')
        Assert-True ($actual.Value -ceq $Expected.Value) ($Message + ': raw registry value changed.')
    }
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
                } elseif ($name -eq 'THIRD-PARTY-NOTICES.md') {
                    $bytes = [Text.Encoding]::UTF8.GetBytes('notices for ' + $script:tag)
                } else { $bytes = [Text.Encoding]::UTF8.GetBytes('license / metadata fixture') }
                $stream.Write($bytes, 0, $bytes.Length)
            } finally { $stream.Dispose() }
        }
        foreach ($item in $Extra) {
            $entry = $zip.CreateEntry($item.Name)
            if ($null -ne $item.Attributes) { $entry.ExternalAttributes = $item.Attributes }
            $stream = $entry.Open()
            try {
                if (-not $item.ContainsKey('Empty') -or -not $item.Empty) { $stream.WriteByte(120) }
            } finally { $stream.Dispose() }
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

if ($script:isNativeWindows) {
    # Snapshot raw data and type; Environment.GetEnvironmentVariable expands REG_EXPAND_SZ.
    $originalPath = Get-TestUserPathState
    Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class RarusEchoInstallerTestEnvironment {
    [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern IntPtr SendMessageTimeout(IntPtr window, uint message, UIntPtr wParam, string lParam, uint flags, uint timeout, out UIntPtr result);
    public static void Notify() {
        UIntPtr result;
        SendMessageTimeout(new IntPtr(0xffff), 0x001a, UIntPtr.Zero, "Environment", 2, 1000, out result);
    }
}
'@
}

try {
    Test-Case 'native interop definitions are available without compiler output' {
        Initialize-RarusEchoNativeMethods
        $native = 'RarusEchoInstallerNativeMethods' -as [type]
        Assert-True ($native.Assembly.IsDynamic) 'Native interop must stay in memory.'
        Assert-True ($null -ne $native.GetMethod('IsWow64Process2')) 'Missing architecture P/Invoke.'
        Assert-True ($null -ne $native.GetMethod('SendMessageTimeout')) 'Missing environment broadcast P/Invoke.'
    }
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
    Test-Case 'ZIP directory size cannot disguise an empty runtime license' {
        $path = Join-Path $root 'false-size.zip'
        New-TestArchive $path '' @(@{ Name = 'runtime-licenses/PHP.txt'; Attributes = 0; Empty = $true }) 'runtime-licenses/PHP.txt'
        $bytes = [IO.File]::ReadAllBytes($path)
        $patched = $false
        for ($offset = 0; $offset -le $bytes.Length - 46; $offset++) {
            if ([BitConverter]::ToUInt32($bytes, $offset) -ne 0x02014b50) { continue }
            $nameLength = [BitConverter]::ToUInt16($bytes, $offset + 28)
            $name = [Text.Encoding]::UTF8.GetString($bytes, $offset + 46, $nameLength)
            if ($name -ceq 'runtime-licenses/PHP.txt') {
                [Array]::Copy([BitConverter]::GetBytes([UInt32]1), 0, $bytes, $offset + 24, 4)
                $patched = $true
                break
            }
        }
        Assert-True $patched 'Could not prepare malformed central-directory size fixture.'
        [IO.File]::WriteAllBytes($path, $bytes)
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'false-size-out') } 'length|size|empty'
    }
    if (-not $script:isNativeWindows) {
        Write-Host ('PASS ' + $script:checks + ' portable checks; native Windows executable/PATH checks require Windows PowerShell 5.1.')
        return
    }
    Test-Case 'native helpers work in a fresh process with Unicode TEMP and cwd' {
        $temporary = Join-Path $root (([string][char]0x044F) + ' compiler files')
        [void][IO.Directory]::CreateDirectory($temporary)
        $probe = @'
$ErrorActionPreference = 'Stop'
$errors = $null
$tokens = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile($env:ECHO_TEST_INSTALLER, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw ($errors | Out-String) }
foreach ($definition in $ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $false)) {
    . ([scriptblock]::Create($definition.Extent.Text))
}
$before = @($env:TMP, $env:TEMP, $env:TMPDIR)
foreach ($attempt in 1..2) {
    if ((Get-RarusEchoPlatform) -cne 'windows-amd64') { throw 'Wrong native architecture in Unicode environment.' }
    Send-RarusEchoEnvironmentChange
}
if (($before -join '|') -cne (@($env:TMP, $env:TEMP, $env:TMPDIR) -join '|')) { throw 'Bootstrap changed caller TEMP variables.' }
if (@([IO.Directory]::EnumerateFileSystemEntries($env:TEMP)).Count) { throw 'Bootstrap left compiler files behind.' }
'@
        $start = New-Object Diagnostics.ProcessStartInfo
        $start.FileName = Join-Path $PSHOME $(if ($PSVersionTable.PSVersion.Major -le 5) { 'powershell.exe' } else { 'pwsh.exe' })
        $start.Arguments = '-NoProfile -NonInteractive -EncodedCommand ' + [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($probe))
        $start.UseShellExecute = $false
        $start.RedirectStandardOutput = $true
        $start.RedirectStandardError = $true
        $start.WorkingDirectory = $temporary
        foreach ($name in @('TMP', 'TEMP', 'TMPDIR')) { $start.EnvironmentVariables[$name] = $temporary }
        $start.EnvironmentVariables['ECHO_TEST_INSTALLER'] = $installer
        $process = [Diagnostics.Process]::new()
        $process.StartInfo = $start
        try {
            [void]$process.Start()
            $stdout = $process.StandardOutput.ReadToEndAsync()
            $stderr = $process.StandardError.ReadToEndAsync()
            if (-not $process.WaitForExit(30000)) { $process.Kill(); throw 'Unicode bootstrap probe timed out.' }
            Assert-True ($process.ExitCode -eq 0) ('Unicode bootstrap probe failed: ' + $stdout.Result + $stderr.Result)
        } finally { $process.Dispose() }
    }
    Test-Case 'native OS architecture is independent of process architecture variables' {
        $originalArchitecture = $env:PROCESSOR_ARCHITECTURE
        $originalWowArchitecture = $env:PROCESSOR_ARCHITEW6432
        try {
            $env:PROCESSOR_ARCHITECTURE = 'ARM64'
            $env:PROCESSOR_ARCHITEW6432 = ''
            Assert-True ((& $script:platformImplementation) -ceq 'windows-amd64') 'Native amd64 OS was misidentified from process environment variables.'
        } finally {
            $env:PROCESSOR_ARCHITECTURE = $originalArchitecture
            $env:PROCESSOR_ARCHITEW6432 = $originalWowArchitecture
        }
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
    $env:JAVA_HOME = Join-Path $root 'Java before'
    $seededPath = [pscustomobject]@{
        Exists = $true
        Value = '%JAVA_HOME%\bin;C:\Existing Tools'
        Kind = [Microsoft.Win32.RegistryValueKind]::ExpandString
    }
    Set-TestUserPathState $seededPath
    Test-Case 'latest install preserves config and no-modify PATH' {
        Install-RarusEcho -NoModifyPath
        Assert-True (Test-Path -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')) 'Default executable was not installed.'
        Assert-True (Test-Path -LiteralPath (Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) 'Package notices were not installed.'
        Assert-True ([IO.File]::ReadAllText($config) -eq 'sensitive-user-config-fixture') 'Configuration changed.'
        Assert-TestUserPathState $seededPath 'NoModifyPath changed user PATH'
    }
    Test-Case 'install and repeat preserve expandable PATH references and registry kind' {
        Install-RarusEcho -Version $script:tag
        $expected = [pscustomobject]@{
            Exists = $true
            Value = $defaultBin + ';' + $seededPath.Value
            Kind = [Microsoft.Win32.RegistryValueKind]::ExpandString
        }
        Assert-TestUserPathState $expected 'Install must preserve REG_EXPAND_SZ and raw variable references'
        Install-RarusEcho -Version $script:tag
        Assert-TestUserPathState $expected 'Repeat install must preserve REG_EXPAND_SZ and raw variable references'
        $env:JAVA_HOME = Join-Path $root 'Java after'
        $key = [Microsoft.Win32.Registry]::CurrentUser.OpenSubKey('Environment')
        try { $expanded = [string]$key.GetValue('Path') } finally { $key.Dispose() }
        Assert-True ($expanded -ceq ($defaultBin + ';' + $env:JAVA_HOME + '\bin;C:\Existing Tools')) 'Changing JAVA_HOME did not change the expanded PATH.'
    }
    Test-Case 'an expanded existing installer entry is idempotent without rewriting raw PATH' {
        $expected = [pscustomobject]@{
            Exists = $true
            Value = '%LOCALAPPDATA%\rarus-echo\bin;%JAVA_HOME%\bin'
            Kind = [Microsoft.Win32.RegistryValueKind]::ExpandString
        }
        Set-TestUserPathState $expected
        Install-RarusEcho -Version $script:tag
        Assert-TestUserPathState $expected 'Expanded installer entry was rewritten or duplicated'
    }
    Test-Case 'ordinary string PATH preserves its kind and literal variable references' {
        $raw = '%JAVA_HOME%\bin;C:\Plain Tools'
        Set-TestUserPathState ([pscustomobject]@{ Exists = $true; Value = $raw; Kind = [Microsoft.Win32.RegistryValueKind]::String })
        Install-RarusEcho -Version $script:tag
        Assert-TestUserPathState ([pscustomobject]@{ Exists = $true; Value = $defaultBin + ';' + $raw; Kind = [Microsoft.Win32.RegistryValueKind]::String }) 'Ordinary string PATH was converted'
    }
    Test-Case 'missing user PATH is created once as an ordinary string' {
        Set-TestUserPathState ([pscustomobject]@{ Exists = $false })
        Install-RarusEcho -Version $script:tag
        Install-RarusEcho -Version $script:tag
        Assert-TestUserPathState ([pscustomobject]@{ Exists = $true; Value = $defaultBin; Kind = [Microsoft.Win32.RegistryValueKind]::String }) 'Missing user PATH was not installed idempotently'
        Set-TestUserPathState $seededPath
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
        Assert-True ([IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) -ceq ('notices for ' + $script:tag)) 'Updated release notices were not installed.'
    }
    $oldExecutable = (Get-FileHash -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')).Hash
    $oldNotice = [IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md'))
    function Assert-OldInstallation {
        Assert-True ((Get-FileHash -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')).Hash -eq $oldExecutable) 'Failure replaced existing executable.'
        Assert-True ([IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) -eq $oldNotice) 'Failure replaced notices.'
        Assert-True ([IO.File]::ReadAllText($config) -eq 'sensitive-user-config-fixture') 'Failure changed configuration.'
    }
    Test-Case 'handled failure after PATH write restores raw expandable value and kind' {
        Set-TestUserPathState $seededPath
        Assert-Fails {
            Install-RarusEcho -Version $script:tag 6>&1 | ForEach-Object {
                if ($_.ToString().StartsWith('Installed ')) { throw 'fixture output sink failure after PATH write' }
            }
        } 'fixture output sink failure'
        Assert-TestUserPathState $seededPath 'Rollback lost the original expandable PATH'
        Assert-OldInstallation
    }
    Test-Case 'handled failure after PATH write restores absence of the registry value' {
        $absent = [pscustomobject]@{ Exists = $false }
        Set-TestUserPathState $absent
        Assert-Fails {
            Install-RarusEcho -Version $script:tag 6>&1 | ForEach-Object {
                if ($_.ToString().StartsWith('Installed ')) { throw 'fixture output sink failure after PATH write' }
            }
        } 'fixture output sink failure'
        Assert-TestUserPathState $absent 'Rollback created a previously absent user PATH'
        Assert-OldInstallation
        Set-TestUserPathState $seededPath
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
        $lockedDirectory = [RarusEchoDirectoryLock]::CreateFile($defaultPackage, [UInt32]2147483648, 3, [IntPtr]::Zero, 3, 0x02000000, [IntPtr]::Zero)
        Assert-True (-not $lockedDirectory.IsInvalid) 'Could not lock package directory for rollback check.'
        try { Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'install|access|process|used|denied' } finally { $lockedDirectory.Dispose() }
        Assert-OldInstallation
    }
    Write-Host ('PASS ' + $script:checks + ' Windows installer checks.')
} finally {
    if ($script:isNativeWindows) {
        Set-TestUserPathState $originalPath
        [RarusEchoInstallerTestEnvironment]::Notify()
    }
    $env:LOCALAPPDATA = $originalLocal
    $env:APPDATA = $originalRoaming
    $env:JAVA_HOME = $originalJavaHome
    if (Test-Path -LiteralPath $root) { Remove-Item -LiteralPath $root -Recurse -Force }
}
