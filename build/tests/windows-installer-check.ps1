# Offline contract checks. Windows PowerShell 5.1 runs the native executable tests.
[CmdletBinding()]
param([string]$Assets, [string]$Version)

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
$script:installerImplementation = ${function:Install-RarusEcho}
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
function New-TestArchive([string]$Path, [string]$Executable, [object[]]$Extra = @(), [string]$Omit = '', [string]$Mode = '') {
    $files = @{}
    $sourceHash = 'b' * 64
    $profileHash = 'c' * 64
    $mediaIdentity = @{ ffmpeg_version = '9.0.2'; source_sha256 = $sourceHash; profile_sha256 = $profileHash }
    $files['build-info.json'] = [Text.Encoding]::UTF8.GetBytes((@{version=$script:tag; source_sha=$script:sha; media=$mediaIdentity} | ConvertTo-Json -Depth 10))
    foreach ($tool in @('ffmpeg', 'ffprobe')) {
        $toolMode = ''
        if ($Mode -eq ('wrong-' + $tool) -or ($tool -eq 'ffmpeg' -and $Mode -eq 'conversion-failure')) { $toolMode = $Mode }
        $fixture = Join-Path $root ($tool + $toolMode + '.exe')
        if ($script:isNativeWindows -and -not (Test-Path -LiteralPath $fixture)) { New-MediaFixture $fixture $tool $toolMode }
        if (Test-Path -LiteralPath $fixture) { $files['libexec/' + $tool + '.exe'] = [IO.File]::ReadAllBytes($fixture) }
        else { $files['libexec/' + $tool + '.exe'] = [Text.Encoding]::UTF8.GetBytes('portable media fixture') }
    }
    $files['media-source.tar.xz'] = [Text.Encoding]::UTF8.GetBytes('corresponding source fixture')
    $files['media-licenses/FFmpeg.txt'] = [Text.Encoding]::UTF8.GetBytes('LGPL')
    $hash = [Security.Cryptography.SHA256]::Create()
    try {
        $binaries = @{}
        foreach ($tool in @('ffmpeg', 'ffprobe')) {
            $pathName = 'libexec/' + $tool + '.exe'
            $binaries[$tool] = @{path=$pathName; sha256=([BitConverter]::ToString($hash.ComputeHash($files[$pathName])).Replace('-', '').ToLowerInvariant())}
        }
        $media = @{schema_version=1; ffmpeg_version='9.0.2'; source_sha256=$sourceHash; profile_sha256=$profileHash;
            target='windows-amd64'; configure_args=@('--disable-everything'); compiler='fixture'; minimum_os='Windows 10'; binaries=$binaries;
            source_package=@{path='media-source.tar.xz'; sha256=([BitConverter]::ToString($hash.ComputeHash($files['media-source.tar.xz'])).Replace('-', '').ToLowerInvariant())}}
        if ($Mode -eq 'wrong-media-target') { $media.target = 'linux-amd64' }
        if ($Mode -eq 'wrong-media-identity') { $media.profile_sha256 = 'd' * 64 }
        if ($Mode -eq 'wrong-schema-type') { $media.schema_version = '1' }
        if ($Mode -eq 'wrong-configure-type') { $media.configure_args = '--disable-everything' }
        $json = $media | ConvertTo-Json -Depth 10
        if ($Mode -eq 'duplicate-media-key') { $json = $json.Replace('"schema_version":', '"schema_version":1,"schema_version":') }
        if ($Mode -eq 'malformed-media-json') { $json = '{not JSON}' }
        $files['media-build-info.json'] = [Text.Encoding]::UTF8.GetBytes($json)
        $records = @()
        foreach ($name in @('libexec/ffmpeg.exe', 'libexec/ffprobe.exe', 'media-build-info.json', 'media-source.tar.xz')) {
            $records += ([BitConverter]::ToString($hash.ComputeHash($files[$name])).Replace('-', '').ToLowerInvariant()) + '  ' + $name
        }
        if ($Mode -eq 'duplicate-media-checksum') { $records += $records[0] }
        $files['media-SHA256SUMS'] = [Text.Encoding]::UTF8.GetBytes(($records -join "`n") + "`n")
        if ($Mode -eq 'tampered-media') { $files['libexec/ffprobe.exe'] = [Text.Encoding]::UTF8.GetBytes('tampered') }
    } finally { $hash.Dispose() }
    $zip = [IO.Compression.ZipFile]::Open($Path, [IO.Compression.ZipArchiveMode]::Create)
    try {
        $names = @('rarus-echo.exe', 'build-info.json', 'runtime-sources.json', 'THIRD-PARTY-NOTICES.md',
            'RARUS-ECHO-LICENSE.txt', 'BITRIX24-CLI-LICENSE.txt', 'YIIPRESS-LICENSE.txt', 'runtime-licenses/PHP.txt') + @($files.Keys | Where-Object { $_ -cne 'build-info.json' })
        foreach ($name in $names) {
            if ($name -eq $Omit) { continue }
            $entry = $zip.CreateEntry($name)
            $permissions = 420
            if ($name -cin @('rarus-echo.exe', 'libexec/ffmpeg.exe', 'libexec/ffprobe.exe')) { $permissions = 493 }
            if ($Mode -eq 'wrong-mode' -and $name -ceq 'libexec/ffprobe.exe') { $permissions = 420 }
            $entry.ExternalAttributes = (($permissions -bor 0x8000) -shl 16)
            $stream = $entry.Open()
            try {
                if ($name -eq 'rarus-echo.exe' -and $Executable) {
                    $bytes = [IO.File]::ReadAllBytes($Executable)
                } elseif ($files.ContainsKey($name)) { $bytes = $files[$name]
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
function New-MediaFixture([string]$Path, [string]$Tool, [string]$Mode = '') {
    $class = 'Media' + [Guid]::NewGuid().ToString('N')
    $mediaVersion = '9.0.2'
    if ($Mode.StartsWith('wrong-')) { $mediaVersion = '8.0' }
    $failure = ''
    if ($Mode -eq 'conversion-failure') { $failure = 'if(args.Length > 0) return 9;' }
    $source = 'using System; public class ' + $class + ' { public static int Main(string[] args) { if(args.Length == 1 && args[0] == "-version") { Console.WriteLine("' + $Tool + ' version ' + $mediaVersion + ' fixture"); return 0; } ' + $failure + ' if("' + $Tool + '" == "ffmpeg") System.IO.File.WriteAllText(args[args.Length-1], "fixture FLAC"); else Console.WriteLine("flac"); return 0; }}'
    Add-Type -TypeDefinition $source -Language CSharp -OutputAssembly $Path -OutputType ConsoleApplication
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
    Test-Case 'media executable mode is required before extraction' {
        $path = Join-Path $root 'wrong-media-mode.zip'
        New-TestArchive $path '' @() '' 'wrong-mode'
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'wrong-media-mode-out') } 'permissions|mode'
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
        Assert-Fails { Expand-RarusEchoArchive $path (Join-Path $root 'empty-runtime-out') } 'runtime licenses|regular file'
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
    $oldToolkit = Join-Path $defaultBin ('.rarus-echo/packages/' + $script:tag + '-' + $script:sha)
    Test-Case 'latest install preserves config and no-modify PATH' {
        Install-RarusEcho -NoModifyPath
        Assert-True (Test-Path -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')) 'Default executable was not installed.'
        Assert-True (Test-Path -LiteralPath (Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) 'Package notices were not installed.'
        Assert-True (Test-Path -LiteralPath (Join-Path $oldToolkit 'libexec/ffprobe.exe')) 'Complete immutable toolkit was not installed.'
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
        Assert-True ([IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) -ceq ('notices for ' + $script:tag)) 'Updated release notices were not installed.'
        Assert-True (Test-Path -LiteralPath (Join-Path $oldToolkit 'libexec/ffprobe.exe')) 'Update removed the previous toolkit.'
    }
    $oldExecutable = (Get-FileHash -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')).Hash
    $oldNotice = [IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md'))
    function Assert-OldInstallation {
        Assert-True ((Get-FileHash -LiteralPath (Join-Path $defaultBin 'rarus-echo.exe')).Hash -eq $oldExecutable) 'Failure replaced existing executable.'
        Assert-True ([IO.File]::ReadAllText((Join-Path $defaultPackage 'THIRD-PARTY-NOTICES.md')) -eq $oldNotice) 'Failure replaced notices.'
        Assert-True ([IO.File]::ReadAllText($config) -eq 'sensitive-user-config-fixture') 'Failure changed configuration.'
    }
    foreach ($mode in @('wrong-media-target', 'wrong-media-identity', 'tampered-media', 'duplicate-media-checksum', 'malformed-media-json', 'duplicate-media-key', 'wrong-schema-type', 'wrong-configure-type', 'wrong-ffmpeg', 'wrong-ffprobe', 'conversion-failure')) {
        Test-Case ('invalid media bundle preserves install: ' + $mode) {
            $script:asset = Join-Path $root ($mode + '.zip')
            New-TestArchive $script:asset $next @() '' $mode
            Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'media|JSON|identity|checksum'
            Assert-OldInstallation
        }
    }
    Test-Case 'existing immutable package conflict is preserved' {
        $script:asset = Join-Path $root 'next.zip'
        $immutableManifest = Join-Path $defaultBin ('.rarus-echo/packages/' + $script:tag + '-' + $script:sha + '/media-build-info.json')
        $originalManifest = [IO.File]::ReadAllText($immutableManifest)
        try {
            [IO.File]::WriteAllText($immutableManifest, 'conflicting manifest')
            Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'conflict|immutable'
            Assert-True ([IO.File]::ReadAllText($immutableManifest) -eq 'conflicting manifest') 'Conflicting immutable manifest was overwritten.'
            Assert-OldInstallation
        } finally { [IO.File]::WriteAllText($immutableManifest, $originalManifest) }
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
        Assert-Fails { Install-RarusEcho -Version $script:tag -InstallDir (Join-Path $root 'staged identity check') -NoModifyPath } 'identity|version'
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
    Test-Case 'failure after activation rolls back complete package and notices' {
        # Fault injection changes only the two transaction operations under test.
        # Keep the real download fixture, hashing, ZIP validation and native programs.
        $fault = $script:installerImplementation.ToString().Replace('$committed = $true', "throw 'injected activation failure'")
        Assert-True ($fault -ne $script:installerImplementation.ToString()) 'Activation fault was not injected.'
        Set-Item -Path function:Install-RarusEcho -Value ([scriptblock]::Create($fault))
        $savedTag = $script:tag
        try {
            $script:tag = 'v1.4.0-rollback.1'
            $rollbackExecutable = Join-Path $root 'rollback.exe'
            New-ConsoleFixture $rollbackExecutable ('RARUS Echo CLI ' + $script:tag + ' (source ' + $script:sha + ')')
            $script:asset = Join-Path $root 'rollback.zip'
            New-TestArchive $script:asset $rollbackExecutable
            Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'injected activation failure'
            Assert-OldInstallation
            Assert-True (-not (Test-Path -LiteralPath (Join-Path $defaultBin ('.rarus-echo/packages/' + $script:tag + '-' + $script:sha)))) 'Failed activation retained a newly installed immutable package.'
        } finally {
            $script:tag = $savedTag
            $script:asset = Join-Path $root 'next.zip'
            Set-Item -Path function:Install-RarusEcho -Value $script:installerImplementation
        }
    }
    $transactionOperations = @(
        @{Name='immutable'; Call='[IO.Directory]::Move($stageBundle, $immutablePackage)'},
        @{Name='notices-backup'; Call='[IO.Directory]::Move($packageDirectory, $backupPackage)'},
        @{Name='notices'; Call='[IO.Directory]::Move($stagePackage, $packageDirectory)'},
        @{Name='executable'; Call='[IO.File]::Replace((Join-Path $stageExecutableDirectory ''rarus-echo.exe''), $executable, $backupExecutable)'},
        @{Name='PATH'; Call='[Environment]::SetEnvironmentVariable(''PATH'', $newPath, ''User'')'}
    )
    $script:operationIndex = 0
    foreach ($operation in $transactionOperations) {
        Test-Case ('exception after successful transaction operation restores previous install: ' + $operation.Name) {
            $script:operationIndex++
            $failureText = 'injected after successful ' + $operation.Name
            $fault = $script:installerImplementation.ToString().Replace($operation.Call, ($operation.Call + '; throw ''' + $failureText + ''''))
            Assert-True ($fault -ne $script:installerImplementation.ToString()) 'Post-operation fault was not injected.'
            Set-Item -Path function:Install-RarusEcho -Value ([scriptblock]::Create($fault))
            $savedTag = $script:tag
            $savedUserPath = [Environment]::GetEnvironmentVariable('PATH', 'User')
            try {
                $script:tag = 'v1.4.0-transaction.' + $script:operationIndex
                $transactionExecutable = Join-Path $root ('transaction-' + $script:operationIndex + '.exe')
                New-ConsoleFixture $transactionExecutable ('RARUS Echo CLI ' + $script:tag + ' (source ' + $script:sha + ')')
                $script:asset = Join-Path $root ('transaction-' + $script:operationIndex + '.zip')
                New-TestArchive $script:asset $transactionExecutable
                # Ensure the real PATH setter runs even after the repeat-install test.
                [Environment]::SetEnvironmentVariable('PATH', (Join-Path $root 'previous user PATH'), 'User')
                $expectedPath = [Environment]::GetEnvironmentVariable('PATH', 'User')
                Assert-Fails { Install-RarusEcho -Version $script:tag } ([Regex]::Escape($failureText))
                Assert-OldInstallation
                Assert-True ([Environment]::GetEnvironmentVariable('PATH', 'User') -ceq $expectedPath) 'Failure after PATH mutation did not restore the previous user PATH.'
                Assert-True (-not (Test-Path -LiteralPath (Join-Path $defaultBin ('.rarus-echo/packages/' + $script:tag + '-' + $script:sha)))) 'Failed transaction retained a newly activated immutable package.'
                Assert-True (@(Get-ChildItem -LiteralPath $defaultBin -Filter '.rarus-echo-backup-*.exe').Count -eq 0) 'Successful rollback retained an executable backup.'
            } finally {
                $script:tag = $savedTag
                $script:asset = Join-Path $root 'next.zip'
                [Environment]::SetEnvironmentVariable('PATH', $savedUserPath, 'User')
                Set-Item -Path function:Install-RarusEcho -Value $script:installerImplementation
            }
        }
    }
    Test-Case 'exception after first executable move removes the entire incomplete install' {
        $operation = '[IO.File]::Move((Join-Path $stageExecutableDirectory ''rarus-echo.exe''), $executable)'
        $fault = $script:installerImplementation.ToString().Replace($operation, ($operation + '; throw ''injected after first executable move'''))
        Assert-True ($fault -ne $script:installerImplementation.ToString()) 'First-install move fault was not injected.'
        Set-Item -Path function:Install-RarusEcho -Value ([scriptblock]::Create($fault))
        $firstInstall = Join-Path $root 'first incomplete install'
        try {
            Assert-Fails { Install-RarusEcho -Version $script:tag -InstallDir $firstInstall -NoModifyPath } 'injected after first executable move'
            Assert-True (-not (Test-Path -LiteralPath (Join-Path $firstInstall 'rarus-echo.exe'))) 'Failed first installation retained a native executable without its package.'
            Assert-True (-not (Test-Path -LiteralPath (Join-Path $firstInstall ('.rarus-echo/packages/' + $script:tag + '-' + $script:sha)))) 'Failed first installation retained its immutable package.'
            Assert-True (-not (Test-Path -LiteralPath (Join-Path $firstInstall 'package'))) 'Failed first installation retained notices.'
            Assert-OldInstallation
        } finally { Set-Item -Path function:Install-RarusEcho -Value $script:installerImplementation }
    }
    Test-Case 'failed rollback retains the previous executable for recovery' {
        $fault = $script:installerImplementation.ToString().Replace('$committed = $true', "throw 'injected activation failure'")
        $fault = $fault.Replace('[IO.File]::Replace($backupExecutable, $executable, $null)', "throw 'injected rollback failure'")
        Assert-True ($fault.Contains("throw 'injected rollback failure'")) 'Rollback fault was not injected.'
        Set-Item -Path function:Install-RarusEcho -Value ([scriptblock]::Create($fault))
        try {
            Assert-Fails { Install-RarusEcho -Version $script:tag -NoModifyPath } 'Rollback could not finish.*Recovery files'
            $backups = @(Get-ChildItem -LiteralPath $defaultBin -Filter '.rarus-echo-backup-*.exe')
            Assert-True ($backups.Count -eq 1) 'Failed rollback did not retain exactly one executable backup.'
            Assert-True ((Get-FileHash -LiteralPath $backups[0].FullName -Algorithm SHA256).Hash -eq $oldExecutable) 'Retained executable backup differs from the old install.'
            Assert-OldInstallation
        } finally { Set-Item -Path function:Install-RarusEcho -Value $script:installerImplementation }
    }
    if ($Assets) {
        Test-Case 'real native complete archive installs and validates tools without host media tools' {
            Assert-True (-not [string]::IsNullOrEmpty($Version)) 'Native archive acceptance requires -Version.'
            $script:tag = $Version
            $script:asset = Join-Path $Assets 'rarus-echo-windows-amd64.zip'
            $script:manifestOverride = [IO.File]::ReadAllText((Join-Path $Assets 'SHA256SUMS'))
            $realCandidate = Join-Path $root 'real-native-candidate'
            Expand-RarusEchoArchive $script:asset $realCandidate
            $realBuild = Read-RarusEchoJson (Join-Path $realCandidate 'build-info.json')
            $native = Join-Path $root ('native complete ' + [char]0x0416 + ' bin')
            $python = (Get-Command python -ErrorAction Stop).Source
            $mediaCheck = Join-Path $PSScriptRoot 'installed-media-check.py'
            $savedProcessPath = $env:PATH
            try {
                $env:PATH = Join-Path $env:SystemRoot 'System32'
                Install-RarusEcho -Version $Version -NoModifyPath
                Install-RarusEcho -Version $Version -NoModifyPath
                & $python $mediaCheck --binary (Join-Path $defaultBin 'rarus-echo.exe')
                if ($LASTEXITCODE -ne 0) { throw 'Default installed native CLI could not resolve and use its matching toolkit.' }
                Install-RarusEcho -Version $Version -InstallDir $native -NoModifyPath
                Install-RarusEcho -Version $Version -InstallDir $native -NoModifyPath
                $package = Join-Path $native ('.rarus-echo/packages/' + $Version + '-' + $realBuild.source_sha)
                Assert-True (Test-Path -LiteralPath (Join-Path $package 'libexec/ffmpeg.exe')) 'Native media executable was not installed.'
                [void](Get-RarusEchoIdentity (Join-Path $native 'rarus-echo.exe') $Version)
                & $python $mediaCheck --binary (Join-Path $native 'rarus-echo.exe')
                if ($LASTEXITCODE -ne 0) { throw 'Unicode custom installed native CLI could not resolve and use its matching toolkit.' }
            } finally { $env:PATH = $savedProcessPath }
        }
    }
    Write-Host ('PASS ' + $script:checks + ' Windows installer checks.')
} finally {
    if ($script:isNativeWindows) { [Environment]::SetEnvironmentVariable('PATH', $originalPath, 'User') }
    $env:LOCALAPPDATA = $originalLocal
    $env:APPDATA = $originalRoaming
    if (Test-Path -LiteralPath $root) { Remove-Item -LiteralPath $root -Recurse -Force }
}
