#requires -Version 5.1
<#
.SYNOPSIS
Installs the standalone RARUS Echo CLI from a public GitHub release.
.EXAMPLE
& ([scriptblock]::Create((Invoke-WebRequest -UseBasicParsing https://raw.githubusercontent.com/mesilov/rarus-echo-php-sdk/main/install.ps1).Content)) -Version v0.5.0
#>
[CmdletBinding()]
param(
    [string]$Version,
    [string]$InstallDir,
    [switch]$NoModifyPath
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

function Get-RarusEchoPlatform {
    if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT) { return 'unsupported' }
    # Process environment and GetNativeSystemInfo can report emulated x64 on ARM64.
    # IsWow64Process2 exposes the native host architecture on modern Windows.
    if (-not ('RarusEchoInstallerPlatform' -as [type])) {
        Add-Type -TypeDefinition @'
using System;
using System.ComponentModel;
using System.Runtime.InteropServices;
public static class RarusEchoInstallerPlatform {
    [DllImport("kernel32.dll", SetLastError = true)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool IsWow64Process2(IntPtr process, out ushort processMachine, out ushort nativeMachine);
    public static ushort NativeMachine() {
        try {
            ushort processMachine, nativeMachine;
            if (!IsWow64Process2(new IntPtr(-1), out processMachine, out nativeMachine))
                throw new Win32Exception(Marshal.GetLastWin32Error());
            return nativeMachine;
        } catch (EntryPointNotFoundException) { return 0; }
    }
}
'@
    }
    $nativeMachine = [RarusEchoInstallerPlatform]::NativeMachine()
    if ($nativeMachine -ne 0) {
        if ($nativeMachine -eq 0x8664) { return 'windows-amd64' }
        return 'unsupported'
    }
    # Windows versions predating IsWow64Process2 also predate Windows on ARM64.
    # PROCESSOR_ARCHITEW6432 identifies the native OS when invoked from 32-bit PowerShell.
    $architecture = $env:PROCESSOR_ARCHITEW6432
    if (-not $architecture) { $architecture = $env:PROCESSOR_ARCHITECTURE }
    if ($architecture -ieq 'AMD64') { return 'windows-amd64' }
    return 'unsupported'
}

function Invoke-RarusEchoDownload([string]$Uri, [string]$Destination) {
    $current = [Uri]$Uri
    $originalTls = [Net.ServicePointManager]::SecurityProtocol
    try {
        [Net.ServicePointManager]::SecurityProtocol = $originalTls -bor [Net.SecurityProtocolType]::Tls12
        for ($redirects = 0; $redirects -le 10; $redirects++) {
            if ($current.Scheme -cne 'https' -or $current.UserInfo) { throw 'Release downloads require HTTPS without URL credentials.' }
            $request = [Net.HttpWebRequest]::Create($current)
            $request.AllowAutoRedirect = $false
            $request.UserAgent = 'rarus-echo-installer'
            $request.Accept = 'application/vnd.github+json, application/octet-stream'
            $request.UseDefaultCredentials = $false
            $request.Timeout = 30000
            $request.ReadWriteTimeout = 30000
            $response = $null
            try {
                $response = $request.GetResponse()
                $status = [int]$response.StatusCode
                if ($status -in @(301, 302, 303, 307, 308)) {
                    $location = $response.Headers['Location']
                    if (-not $location) { throw 'Release download returned a redirect without a destination.' }
                    $current = New-Object Uri($current, $location)
                    continue
                }
                if ($status -ne 200) { throw ('Release download returned HTTP ' + $status + '.') }
                $inputStream = $response.GetResponseStream()
                $outputStream = [IO.File]::Open($Destination, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
                try { $inputStream.CopyTo($outputStream) } finally { $outputStream.Dispose(); $inputStream.Dispose() }
                return
            } finally { if ($null -ne $response) { $response.Dispose() } }
        }
        throw 'Release download exceeded the HTTPS redirect limit.'
    } finally { [Net.ServicePointManager]::SecurityProtocol = $originalTls }
}

function Assert-RarusEchoChecksum([string]$Archive, [string]$Manifest, [string]$AssetName) {
    $selected = @()
    foreach ($line in [IO.File]::ReadAllLines($Manifest)) {
        if (-not $line) { continue }
        if ($line -cnotmatch '^([0-9a-fA-F]{64}) [ *]([^\s]+)$') { throw 'Invalid SHA256SUMS checksum record.' }
        if ($Matches[2] -ceq $AssetName) { $selected += $Matches[1] }
    }
    if ($selected.Count -ne 1) { throw 'SHA256SUMS must contain exactly one checksum for the selected archive.' }
    $actual = (Get-FileHash -LiteralPath $Archive -Algorithm SHA256).Hash
    if ($actual -ine $selected[0]) { throw 'Release archive checksum does not match SHA256SUMS.' }
}

function Expand-RarusEchoArchive([string]$Archive, [string]$Destination) {
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $required = @('rarus-echo.exe', 'build-info.json', 'runtime-sources.json', 'THIRD-PARTY-NOTICES.md',
        'RARUS-ECHO-LICENSE.txt', 'BITRIX24-CLI-LICENSE.txt', 'YIIPRESS-LICENSE.txt',
        'libexec/ffmpeg.exe', 'libexec/ffprobe.exe', 'media-build-info.json', 'media-SHA256SUMS', 'media-source.tar.xz')
    $members = New-Object 'System.Collections.Generic.Dictionary[string,bool]' ([StringComparer]::OrdinalIgnoreCase)
    $spellings = New-Object 'System.Collections.Generic.Dictionary[string,string]' ([StringComparer]::OrdinalIgnoreCase)
    $runtimeFiles = 0
    $mediaLicenseFiles = 0
    $zip = [IO.Compression.ZipFile]::OpenRead($Archive)
    try {
        foreach ($entry in $zip.Entries) {
            $name = $entry.FullName
            if (-not $name -or $name -match '[\\:\x00-\x1f<>"|?*]' -or $name.StartsWith('/')) { throw 'Unsafe ZIP archive member name.' }
            $isDirectory = $name.EndsWith('/')
            $normalized = $name.TrimEnd('/')
            $parts = $normalized.Split('/')
            foreach ($part in $parts) {
                if (-not $part -or $part -eq '.' -or $part -eq '..' -or $part -match '[. ]$' -or
                    $part -match '^(?i:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)') {
                    throw 'Unsafe ZIP archive member path.'
                }
            }
            if ($members.ContainsKey($normalized)) { throw 'Duplicate or case-colliding ZIP archive member.' }
            $members.Add($normalized, $isDirectory)
            $prefix = ''
            foreach ($part in $parts) {
                if ($prefix) { $prefix += '/' }
                $prefix += $part
                if ($spellings.ContainsKey($prefix)) {
                    if ($spellings[$prefix] -cne $prefix) { throw 'Case-colliding ZIP archive member paths.' }
                } else { $spellings.Add($prefix, $prefix) }
            }
            $unixType = ($entry.ExternalAttributes -shr 16) -band 0xF000
            $unixMode = ($entry.ExternalAttributes -shr 16) -band 0xFFF
            $dosAttributes = $entry.ExternalAttributes -band 0xFFFF
            if (($unixType -notin @(0, 0x8000, 0x4000)) -or ($dosAttributes -band 0x408) -ne 0 -or
                ($unixType -eq 0x4000 -and -not $isDirectory) -or
                ($unixType -eq 0x8000 -and $isDirectory) -or
                (($dosAttributes -band 0x10) -ne 0 -and -not $isDirectory)) {
                throw 'ZIP archive links, special files and inconsistent member types are not allowed.'
            }
            if ($isDirectory) { throw 'ZIP archive must contain regular file entries only.' }
            $isExecutable = $name -cin @('rarus-echo.exe', 'libexec/ffmpeg.exe', 'libexec/ffprobe.exe')
            if (($unixType -ne 0 -or $unixMode -ne 0) -and (($isExecutable -and $unixMode -ne 493) -or (-not $isExecutable -and $unixMode -ne 420))) {
                throw 'ZIP archive has incorrect executable/data permissions.'
            }
            $isRuntime = $normalized.StartsWith('runtime-licenses/', [StringComparison]::Ordinal)
            $isMediaLicense = $normalized.StartsWith('media-licenses/', [StringComparison]::Ordinal)
            if ($isDirectory) {
                if ($normalized -cne 'runtime-licenses' -and -not $isRuntime) { throw 'Unexpected ZIP archive directory.' }
            } else {
                if ($required -cnotcontains $name -and -not $isRuntime -and -not $isMediaLicense) { throw 'Unexpected ZIP archive payload.' }
                if ($entry.Length -le 0) { throw 'Required archive files and licenses must not be empty.' }
                if ($isRuntime) { $runtimeFiles++ }
                if ($isMediaLicense) { $mediaLicenseFiles++ }
            }
        }
        foreach ($name in $members.Keys) {
            $parent = $name
            while ($parent.Contains('/')) {
                $parent = $parent.Substring(0, $parent.LastIndexOf('/'))
                if ($members.ContainsKey($parent) -and -not $members[$parent]) { throw 'ZIP archive member collides with a file path.' }
            }
        }
        foreach ($name in $required) {
            if (-not $members.ContainsKey($name) -or $members[$name]) { throw ('Missing required archive file: ' + $name) }
        }
        if ($runtimeFiles -eq 0) { throw 'Missing required archive runtime licenses.' }
        if ($mediaLicenseFiles -eq 0) { throw 'Missing required archive media licenses.' }
        if (Test-Path -LiteralPath $Destination) { throw 'Archive extraction destination already exists.' }
        [void][IO.Directory]::CreateDirectory($Destination)
        foreach ($entry in $zip.Entries) {
            if ($entry.FullName.EndsWith('/')) { continue }
            $target = Join-Path $Destination $entry.FullName.Replace('/', [IO.Path]::DirectorySeparatorChar)
            [void][IO.Directory]::CreateDirectory((Split-Path $target -Parent))
            $inputStream = $entry.Open()
            $outputStream = [IO.File]::Open($target, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
            try {
                $inputStream.CopyTo($outputStream)
                if ($outputStream.Length -le 0 -or $outputStream.Length -ne $entry.Length) {
                    throw 'Extracted archive member size differs from the ZIP directory or is empty.'
                }
            } finally { $outputStream.Dispose(); $inputStream.Dispose() }
        }
    } finally { $zip.Dispose() }
}

function Read-RarusEchoJson([string]$Path) {
    # ConvertFrom-Json accepts duplicate names in Windows PowerShell 5.1. Validate
    # the full grammar and duplicate keys before using its convenient object model.
    if (-not ('RarusEchoInstallerJson' -as [type])) {
        Add-Type -TypeDefinition @'
using System;
using System.Collections.Generic;
using System.Text.RegularExpressions;
public sealed class RarusEchoInstallerJson {
    private readonly string text; private int position;
    private RarusEchoInstallerJson(string input) { text = input; }
    private void Fail() { throw new FormatException("Invalid media/build JSON or duplicate object key."); }
    private void Space() { while(position < text.Length && " \t\r\n".IndexOf(text[position]) >= 0) position++; }
    private char Take() { if(position >= text.Length) Fail(); return text[position++]; }
    private string String(bool key) {
        if(Take() != '"') Fail(); int start = position;
        while(position < text.Length) {
            char c = Take(); if(c == '"') return text.Substring(start, position-start-1);
            if(c < 32) Fail();
            if(c == '\\') {
                if(key) Fail(); char escape = Take();
                if(escape == 'u') { for(int i=0;i<4;i++) if(!Uri.IsHexDigit(Take())) Fail(); }
                else if("\"\\/bfnrt".IndexOf(escape) < 0) Fail();
            }
        } Fail(); return null;
    }
    private void Value(int depth) {
        if(depth > 64) Fail(); Space(); if(position >= text.Length) Fail(); char c = text[position];
        if(c == '{') {
            position++; Space(); if(position < text.Length && text[position] == '}') { position++; return; }
            var keys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            while(true) {
                Space(); string name = String(true); if(!keys.Add(name)) Fail();
                Space(); if(Take() != ':') Fail(); Value(depth+1); Space(); c = Take();
                if(c == '}') return; if(c != ',') Fail();
            }
        } else if(c == '[') {
            position++; Space(); if(position < text.Length && text[position] == ']') { position++; return; }
            while(true) { Value(depth+1); Space(); c = Take(); if(c == ']') return; if(c != ',') Fail(); }
        } else if(c == '"') String(false);
        else {
            int start = position;
            while(position < text.Length && " \t\r\n,]}".IndexOf(text[position]) < 0) position++;
            string token = text.Substring(start, position-start);
            if(!Regex.IsMatch(token, @"\A(?:true|false|null|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)\z")) Fail();
        }
    }
    public static void Validate(string text) {
        var parser = new RarusEchoInstallerJson(text); parser.Space();
        if(parser.position >= text.Length || text[parser.position] != '{') parser.Fail();
        parser.Value(0); parser.Space(); if(parser.position != text.Length) parser.Fail();
    }
}
'@
    }
    $text = [IO.File]::ReadAllText($Path)
    if ($text.Length -gt 1048576) { throw 'Media/build JSON exceeds the installer limit.' }
    [RarusEchoInstallerJson]::Validate($text)
    return ($text | ConvertFrom-Json)
}

function Invoke-RarusEchoMedia([string]$Executable, [string[]]$Arguments) {
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $Executable
    $quoted = foreach ($argument in $Arguments) {
        $escaped = [Regex]::Replace($argument, '(\\*)"', '$1$1\"')
        $escaped = [Regex]::Replace($escaped, '(\\+)$', '$1$1')
        '"' + $escaped + '"'
    }
    $start.Arguments = $quoted -join ' '
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    foreach ($key in @($start.EnvironmentVariables.Keys)) {
        if ($key -like 'RARUS_ECHO_*') { $start.EnvironmentVariables.Remove($key) }
    }
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    try {
        [void]$process.Start()
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        if (-not $process.WaitForExit(30000)) {
            $process.Kill(); $process.WaitForExit()
            throw 'Bundled media acceptance timed out.'
        }
        if ($process.ExitCode -ne 0 -or $stderr.Result -ne '') { throw 'Bundled media command failed acceptance.' }
        return $stdout.Result.TrimEnd([char[]]"`r`n")
    } finally { $process.Dispose() }
}

function Assert-RarusEchoMedia([string]$Directory, [string]$Temporary) {
    $required = @('libexec/ffmpeg.exe', 'libexec/ffprobe.exe', 'media-build-info.json', 'media-source.tar.xz')
    $records = New-Object 'System.Collections.Generic.Dictionary[string,string]' ([StringComparer]::Ordinal)
    foreach ($line in [IO.File]::ReadAllLines((Join-Path $Directory 'media-SHA256SUMS'))) {
        if ($line -cnotmatch '^([0-9a-f]{64})  ([^\s]+)$' -or $required -cnotcontains $Matches[2] -or $records.ContainsKey($Matches[2])) {
            throw 'Media checksum manifest must contain exactly four unique valid records.'
        }
        $records.Add($Matches[2], $Matches[1])
    }
    if ($records.Count -ne 4) { throw 'Media checksum manifest must contain exactly four records.' }
    foreach ($name in $required) {
        if ((Get-FileHash -LiteralPath (Join-Path $Directory $name) -Algorithm SHA256).Hash -ine $records[$name]) { throw ('Media checksum mismatch: ' + $name) }
    }
    $media = Read-RarusEchoJson (Join-Path $Directory 'media-build-info.json')
    $build = Read-RarusEchoJson (Join-Path $Directory 'build-info.json')
    foreach ($name in @('schema_version', 'target', 'ffmpeg_version', 'source_sha256', 'profile_sha256', 'compiler', 'minimum_os', 'configure_args', 'binaries', 'source_package')) {
        if (@($media.PSObject.Properties | Where-Object { $_.Name -ceq $name }).Count -ne 1) { throw ('Media manifest missing canonical property: ' + $name) }
    }
    if (($media.schema_version -isnot [int] -and $media.schema_version -isnot [long]) -or $media.schema_version -ne 1 -or
        $media.target -cne 'windows-amd64' -or $media.ffmpeg_version -cnotmatch '^[0-9]+(?:\.[0-9]+)+$') { throw 'Media manifest schema, target or version identity is invalid.' }
    if ($media.configure_args -isnot [Array] -or $media.compiler -isnot [string] -or -not $media.compiler -or $media.minimum_os -isnot [string] -or -not $media.minimum_os) {
        throw 'Media manifest configure arguments, compiler or minimum OS is invalid.'
    }
    foreach ($name in @('ffmpeg_version', 'source_sha256', 'profile_sha256')) {
        if ($media.$name -cne $build.media.$name) { throw ('CLI/media identity mismatch: ' + $name) }
        if ($name -cne 'ffmpeg_version' -and $media.$name -cnotmatch '^[0-9a-f]{64}$') { throw 'Invalid media identity digest.' }
    }
    foreach ($tool in @('ffmpeg', 'ffprobe')) {
        $path = 'libexec/' + $tool + '.exe'
        if ($media.binaries.$tool.path -cne $path -or $media.binaries.$tool.sha256 -cne $records[$path]) { throw 'Media binary identity/hash mismatch.' }
        $reported = Invoke-RarusEchoMedia (Join-Path $Directory $path) @('-version')
        if ($reported -cnotmatch ('\A' + $tool + ' version ' + [Regex]::Escape($media.ffmpeg_version) + ' ')) { throw ('Bundled ' + $tool + ' version identity mismatch.') }
    }
    if ($media.source_package.path -cne 'media-source.tar.xz' -or $media.source_package.sha256 -cne $records['media-source.tar.xz']) { throw 'Media corresponding source identity/hash mismatch.' }
    $pcm = Join-Path $Temporary 'installer-check.pcm'
    $flac = Join-Path $Temporary 'installer-check.flac'
    [IO.File]::WriteAllBytes($pcm, (New-Object byte[] 16))
    if (Test-Path -LiteralPath $flac) { [IO.File]::Delete($flac) }
    [void](Invoke-RarusEchoMedia (Join-Path $Directory 'libexec/ffmpeg.exe') @('-nostdin', '-v', 'error', '-f', 's16le', '-ar', '8000', '-ac', '1', '-i', $pcm, '-c:a', 'flac', $flac))
    if (-not (Test-Path -LiteralPath $flac -PathType Leaf) -or (Get-Item -LiteralPath $flac).Length -le 0) { throw 'Bundled media conversion produced no output.' }
    $codec = Invoke-RarusEchoMedia (Join-Path $Directory 'libexec/ffprobe.exe') @('-v', 'error', '-select_streams', 'a:0', '-show_entries', 'stream=codec_name', '-of', 'default=nw=1:nk=1', $flac)
    if ($codec -cne 'flac') { throw 'Bundled media probe did not confirm FLAC audio.' }
    return $build
}

function Get-RarusEchoIdentity([string]$Executable, [string]$Tag) {
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $Executable
    $start.Arguments = '--version --no-ansi'
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    foreach ($key in @($start.EnvironmentVariables.Keys)) {
        if ($key -like 'RARUS_ECHO_*') { $start.EnvironmentVariables.Remove($key) }
    }
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    try {
        [void]$process.Start()
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        if (-not $process.WaitForExit(30000)) {
            $process.Kill()
            $process.WaitForExit()
            throw 'Candidate CLI version check timed out.'
        }
        $output = $stdout.Result.TrimEnd([char[]]"`r`n")
        if ($process.ExitCode -ne 0 -or $stderr.Result -ne '' -or
            $output -cnotmatch ('\ARARUS Echo CLI ' + [Regex]::Escape($Tag) + ' \(source [0-9a-f]{40}\)\z')) {
            throw 'Candidate CLI version identity does not match the selected release tag and source SHA.'
        }
        return $output
    } finally { $process.Dispose() }
}

function Assert-RarusEchoNoLinks([string]$Directory) {
    foreach ($item in Get-ChildItem -LiteralPath $Directory -Force) {
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Immutable package must not contain links or reparse points.' }
        if ($item.PSIsContainer) { Assert-RarusEchoNoLinks $item.FullName }
    }
}

function Install-RarusEcho([string]$Version, [string]$InstallDir, [switch]$NoModifyPath) {
    if ((Get-RarusEchoPlatform) -cne 'windows-amd64') { throw 'Unsupported platform. This installer supports Windows amd64; Linux amd64/arm64 and macOS arm64 use install.sh.' }
    if ($Version -and $Version -cnotmatch '\A[A-Za-z0-9][A-Za-z0-9._+-]*\z') { throw 'Invalid release version tag.' }
    if ($Version -and $Version.Contains('..')) { throw 'Invalid release version tag.' }
    $customDirectory = -not [string]::IsNullOrEmpty($InstallDir)
    if (-not $customDirectory) {
        if (-not $env:LOCALAPPDATA) { throw 'LOCALAPPDATA is unavailable; supply an absolute -InstallDir.' }
        $InstallDir = Join-Path $env:LOCALAPPDATA 'rarus-echo/bin'
    }
    if ($InstallDir -match '[;\x00-\x1f\x7f]') { throw 'Installation directory must be a single PATH entry without semicolons or control characters.' }
    if ($InstallDir -notmatch '^(?:[A-Za-z]:[\\/]|\\\\[^\\/]+[\\/][^\\/]+(?:[\\/]|$))') { throw 'Installation directory must be an absolute Windows path (including LOCALAPPDATA when using the default).' }
    $InstallDir = [IO.Path]::GetFullPath($InstallDir)
    if ($customDirectory) { $packageDirectory = Join-Path $InstallDir 'package' }
    else { $packageDirectory = Join-Path (Split-Path $InstallDir -Parent) 'package' }
    $executable = Join-Path $InstallDir 'rarus-echo.exe'
    $bundleRoot = Join-Path $InstallDir '.rarus-echo'
    $packages = Join-Path $bundleRoot 'packages'
    foreach ($target in @($InstallDir, $executable, $packageDirectory, $bundleRoot, $packages)) {
        if (Test-Path -LiteralPath $target) {
            $item = Get-Item -LiteralPath $target -Force
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Installer destinations must not be links or reparse points.' }
            if (($target -eq $executable) -eq $item.PSIsContainer) { throw 'An installer destination has an unexpected file type.' }
        }
    }
    $identifier = [Guid]::NewGuid().ToString('N')
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ('rarus-echo-install-' + $identifier)
    $stageExecutableDirectory = Join-Path $InstallDir ('.rarus-echo-stage-' + $identifier)
    $packageParent = Split-Path $packageDirectory -Parent
    $stagePackage = Join-Path $packageParent ('.rarus-echo-package-stage-' + $identifier)
    $stageBundle = Join-Path $packages ('.stage-' + $identifier)
    $immutablePackage = $null
    $backupExecutable = Join-Path $InstallDir ('.rarus-echo-backup-' + $identifier + '.exe')
    $backupPackage = Join-Path $packageParent ('.rarus-echo-package-backup-' + $identifier)
    $oldExecutableMoved = $false
    $oldPackageMoved = $false
    $newExecutableMoved = $false
    $newPackageMoved = $false
    $newBundleMoved = $false
    $previousExecutableHash = $null
    $pathChanged = $false
    $committed = $false
    $oldUserPath = [Environment]::GetEnvironmentVariable('PATH', 'User')
    [void][IO.Directory]::CreateDirectory($temporary)
    try {
        $tag = $Version
        if (-not $tag) {
            $latest = Join-Path $temporary 'release.json'
            Invoke-RarusEchoDownload 'https://api.github.com/repos/mesilov/rarus-echo-php-sdk/releases/latest' $latest
            $release = [IO.File]::ReadAllText($latest) | ConvertFrom-Json
            if (-not $release.tag_name -or $release.draft -ne $false -or $release.prerelease -ne $false) { throw 'Latest public stable release metadata is invalid.' }
            $tag = [string]$release.tag_name
            if ($tag -cnotmatch '\A[A-Za-z0-9][A-Za-z0-9._+-]*\z' -or $tag.Contains('..')) { throw 'Latest release has an unsafe version tag.' }
        }
        $asset = 'rarus-echo-windows-amd64.zip'
        $releaseUrl = 'https://github.com/mesilov/rarus-echo-php-sdk/releases/download/' + [Uri]::EscapeDataString($tag) + '/'
        $archive = Join-Path $temporary $asset
        $manifest = Join-Path $temporary 'SHA256SUMS'
        try {
            Invoke-RarusEchoDownload ($releaseUrl + 'SHA256SUMS') $manifest
            Invoke-RarusEchoDownload ($releaseUrl + $asset) $archive
        } catch { throw ('Could not download standalone assets for public release ' + $tag + '. The release must publish ' + $asset + ' and SHA256SUMS. ' + $_.Exception.Message) }
        Assert-RarusEchoChecksum $archive $manifest $asset
        $candidate = Join-Path $temporary 'candidate'
        Expand-RarusEchoArchive $archive $candidate
        $build = Assert-RarusEchoMedia $candidate $temporary
        $identity = Get-RarusEchoIdentity (Join-Path $candidate 'rarus-echo.exe') $tag
        $sourceSha = [Regex]::Match($identity, '\(source ([0-9a-f]{40})\)$').Groups[1].Value
        if ($build.version -cne $tag -or $build.source_sha -cne $sourceSha) { throw 'CLI build metadata identity mismatch.' }
        $immutablePackage = Join-Path $packages ($tag + '-' + $sourceSha)
        [void][IO.Directory]::CreateDirectory($packages)
        if (Test-Path -LiteralPath $immutablePackage) {
            $existing = Get-Item -LiteralPath $immutablePackage -Force
            if (-not $existing.PSIsContainer -or ($existing.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Immutable package must be a directory without reparse points.' }
            Assert-RarusEchoNoLinks $immutablePackage
            $candidateFiles = @(Get-ChildItem -LiteralPath $candidate -Recurse -File -Force)
            $existingFiles = @(Get-ChildItem -LiteralPath $immutablePackage -Recurse -File -Force)
            if ($candidateFiles.Count -ne $existingFiles.Count) { throw 'Existing immutable package conflicts with the release.' }
            foreach ($file in $candidateFiles) {
                $relative = $file.FullName.Substring($candidate.Length).TrimStart('\', '/')
                $installedFile = Join-Path $immutablePackage $relative
                if (-not (Test-Path -LiteralPath $installedFile -PathType Leaf) -or
                    (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash -cne (Get-FileHash -LiteralPath $installedFile -Algorithm SHA256).Hash) {
                    throw 'Existing immutable package conflicts with the release; it was not overwritten.'
                }
            }
            [void](Assert-RarusEchoMedia $immutablePackage $temporary)
        } else {
            [void][IO.Directory]::CreateDirectory($stageBundle)
            foreach ($item in Get-ChildItem -LiteralPath $candidate -Force) { Copy-Item -LiteralPath $item.FullName -Destination $stageBundle -Recurse }
            [void](Assert-RarusEchoMedia $stageBundle $temporary)
        }
        [void][IO.Directory]::CreateDirectory($stageExecutableDirectory)
        [void][IO.Directory]::CreateDirectory($stagePackage)
        [IO.File]::Copy((Join-Path $candidate 'rarus-echo.exe'), (Join-Path $stageExecutableDirectory 'rarus-echo.exe'))
        foreach ($item in Get-ChildItem -LiteralPath $candidate -Force) {
            if ($item.Name -cnotin @('rarus-echo.exe', 'libexec', 'media-source.tar.xz')) { Copy-Item -LiteralPath $item.FullName -Destination $stagePackage -Recurse }
        }
        $stagedIdentity = Get-RarusEchoIdentity (Join-Path $stageExecutableDirectory 'rarus-echo.exe') $tag
        if ($stagedIdentity -cne $identity) { throw 'Staged CLI version identity differs from the verified candidate.' }
        if (Test-Path -LiteralPath $executable) { $previousExecutableHash = (Get-FileHash -LiteralPath $executable -Algorithm SHA256).Hash }
        # Record rollback intent before each native mutation: a pipeline stop or
        # exception can occur after the call succeeds and before the next assignment.
        if (Test-Path -LiteralPath $stageBundle) { $newBundleMoved = $true; [IO.Directory]::Move($stageBundle, $immutablePackage) }
        if (Test-Path -LiteralPath $packageDirectory) { $oldPackageMoved = $true; [IO.Directory]::Move($packageDirectory, $backupPackage) }
        $newPackageMoved = $true
        [IO.Directory]::Move($stagePackage, $packageDirectory)
        $newExecutableMoved = $true
        if (Test-Path -LiteralPath $executable) {
            # File.Replace is atomic on this filesystem and fails if Windows has
            # locked the executable. Previous media directories are never touched.
            $oldExecutableMoved = $true
            try { [IO.File]::Replace((Join-Path $stageExecutableDirectory 'rarus-echo.exe'), $executable, $backupExecutable) }
            catch { throw ('Cannot replace rarus-echo.exe; close running CLI processes and retry. ' + $_.Exception.Message) }
        } else { [IO.File]::Move((Join-Path $stageExecutableDirectory 'rarus-echo.exe'), $executable) }
        if (-not $NoModifyPath) {
            $alreadyPresent = $false
            foreach ($entry in ($oldUserPath -split ';')) {
                $expanded = [Environment]::ExpandEnvironmentVariables($entry.Trim().Trim('"'))
                if ($expanded.TrimEnd('\', '/') -ieq $InstallDir.TrimEnd('\', '/')) { $alreadyPresent = $true }
            }
            if (-not $alreadyPresent) {
                $newPath = $InstallDir
                if ($oldUserPath) { $newPath += ';' + $oldUserPath }
                $pathChanged = $true
                [Environment]::SetEnvironmentVariable('PATH', $newPath, 'User')
            }
        }
        $committed = $true
        Write-Host ('Installed ' + $identity + ' to ' + $executable)
        Write-Host ('Distribution notices: ' + $packageDirectory)
        Write-Host ('Complete package: ' + $immutablePackage)
        if ($NoModifyPath) { Write-Host 'User PATH was not changed.' } else { Write-Host 'User PATH is configured for new terminals.' }
        Write-Host ('For this PowerShell session, run: $env:PATH = ''' + $InstallDir.Replace("'", "''") + ';'' + $env:PATH')
    } catch {
        $failure = $_.Exception.Message
        $rollbackFailures = @()
        try {
            if ($oldExecutableMoved) {
                if (Test-Path -LiteralPath $backupExecutable -PathType Leaf) {
                    if (Test-Path -LiteralPath $executable -PathType Leaf) { [IO.File]::Replace($backupExecutable, $executable, $null) }
                    else { [IO.File]::Move($backupExecutable, $executable) }
                } elseif (-not (Test-Path -LiteralPath $executable -PathType Leaf) -or
                    (Get-FileHash -LiteralPath $executable -Algorithm SHA256).Hash -cne $previousExecutableHash) {
                    throw 'Previous executable differs or is missing, and its recovery backup is unavailable.'
                }
            } elseif ($newExecutableMoved -and (Test-Path -LiteralPath $executable)) { [IO.File]::Delete($executable) }
        } catch { $rollbackFailures += $_.Exception.Message }
        try {
            if ($newPackageMoved -and (Test-Path -LiteralPath $packageDirectory)) { Remove-Item -LiteralPath $packageDirectory -Recurse -Force }
            if ($oldPackageMoved -and (Test-Path -LiteralPath $backupPackage)) { [IO.Directory]::Move($backupPackage, $packageDirectory) }
        } catch { $rollbackFailures += $_.Exception.Message }
        try { if ($pathChanged -and [Environment]::GetEnvironmentVariable('PATH', 'User') -cne $oldUserPath) { [Environment]::SetEnvironmentVariable('PATH', $oldUserPath, 'User') } }
        catch { $rollbackFailures += $_.Exception.Message }
        if ($rollbackFailures.Count -eq 0 -and $newBundleMoved -and (Test-Path -LiteralPath $immutablePackage)) {
            try { Remove-Item -LiteralPath $immutablePackage -Recurse -Force }
            catch { $rollbackFailures += $_.Exception.Message }
        }
        if ($rollbackFailures.Count -ne 0) { throw ('Installation failed: ' + $failure + ' Rollback could not finish. Recovery files are retained at ' + $backupExecutable + ', ' + $backupPackage + ' and ' + $immutablePackage + '. ' + ($rollbackFailures -join ' ')) }
        throw ('Installation failed; any previous executable and notices were preserved. ' + $failure)
    } finally {
        foreach ($path in @($temporary, $stageExecutableDirectory, $stagePackage, $stageBundle)) {
            if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Recurse -Force -ErrorAction SilentlyContinue }
        }
        if ($committed) {
            foreach ($path in @($backupExecutable, $backupPackage)) {
                if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Recurse -Force -ErrorAction SilentlyContinue }
            }
        }
    }
}

Install-RarusEcho -Version $Version -InstallDir $InstallDir -NoModifyPath:$NoModifyPath
