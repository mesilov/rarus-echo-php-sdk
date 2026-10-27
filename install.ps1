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
        'RARUS-ECHO-LICENSE.txt', 'BITRIX24-CLI-LICENSE.txt', 'YIIPRESS-LICENSE.txt')
    $members = New-Object 'System.Collections.Generic.Dictionary[string,bool]' ([StringComparer]::OrdinalIgnoreCase)
    $spellings = New-Object 'System.Collections.Generic.Dictionary[string,string]' ([StringComparer]::OrdinalIgnoreCase)
    $runtimeFiles = 0
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
            $dosAttributes = $entry.ExternalAttributes -band 0xFFFF
            if (($unixType -notin @(0, 0x8000, 0x4000)) -or ($dosAttributes -band 0x408) -ne 0 -or
                ($unixType -eq 0x4000 -and -not $isDirectory) -or
                ($unixType -eq 0x8000 -and $isDirectory) -or
                (($dosAttributes -band 0x10) -ne 0 -and -not $isDirectory)) {
                throw 'ZIP archive links, special files and inconsistent member types are not allowed.'
            }
            $isRuntime = $normalized.StartsWith('runtime-licenses/', [StringComparison]::Ordinal)
            if ($isDirectory) {
                if ($normalized -cne 'runtime-licenses' -and -not $isRuntime) { throw 'Unexpected ZIP archive directory.' }
            } else {
                if ($required -cnotcontains $name -and -not $isRuntime) { throw 'Unexpected ZIP archive payload.' }
                if ($entry.Length -le 0) { throw 'Required archive files and licenses must not be empty.' }
                if ($isRuntime) { $runtimeFiles++ }
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

function Get-RarusEchoIdentity([string]$Executable, [string]$Tag) {
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $Executable
    $start.Arguments = '--version --no-ansi'
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
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
    foreach ($target in @($InstallDir, $executable, $packageDirectory)) {
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
    $backupExecutable = Join-Path $InstallDir ('.rarus-echo-backup-' + $identifier + '.exe')
    $backupPackage = Join-Path $packageParent ('.rarus-echo-package-backup-' + $identifier)
    $oldExecutableMoved = $false
    $oldPackageMoved = $false
    $newExecutableMoved = $false
    $newPackageMoved = $false
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
        $identity = Get-RarusEchoIdentity (Join-Path $candidate 'rarus-echo.exe') $tag
        [void][IO.Directory]::CreateDirectory($stageExecutableDirectory)
        [void][IO.Directory]::CreateDirectory($stagePackage)
        [IO.File]::Copy((Join-Path $candidate 'rarus-echo.exe'), (Join-Path $stageExecutableDirectory 'rarus-echo.exe'))
        foreach ($item in Get-ChildItem -LiteralPath $candidate -Force) {
            if ($item.Name -cne 'rarus-echo.exe') { Copy-Item -LiteralPath $item.FullName -Destination $stagePackage -Recurse }
        }
        $stagedIdentity = Get-RarusEchoIdentity (Join-Path $stageExecutableDirectory 'rarus-echo.exe') $tag
        if ($stagedIdentity -cne $identity) { throw 'Staged CLI version identity differs from the verified candidate.' }
        if (Test-Path -LiteralPath $executable) { [IO.File]::Move($executable, $backupExecutable); $oldExecutableMoved = $true }
        if (Test-Path -LiteralPath $packageDirectory) { [IO.Directory]::Move($packageDirectory, $backupPackage); $oldPackageMoved = $true }
        [IO.File]::Move((Join-Path $stageExecutableDirectory 'rarus-echo.exe'), $executable)
        $newExecutableMoved = $true
        [IO.Directory]::Move($stagePackage, $packageDirectory)
        $newPackageMoved = $true
        if (-not $NoModifyPath) {
            $alreadyPresent = $false
            foreach ($entry in ($oldUserPath -split ';')) {
                $expanded = [Environment]::ExpandEnvironmentVariables($entry.Trim().Trim('"'))
                if ($expanded.TrimEnd('\', '/') -ieq $InstallDir.TrimEnd('\', '/')) { $alreadyPresent = $true }
            }
            if (-not $alreadyPresent) {
                $newPath = $InstallDir
                if ($oldUserPath) { $newPath += ';' + $oldUserPath }
                [Environment]::SetEnvironmentVariable('PATH', $newPath, 'User')
                $pathChanged = $true
            }
        }
        $committed = $true
        Write-Host ('Installed ' + $identity + ' to ' + $executable)
        Write-Host ('Distribution notices: ' + $packageDirectory)
        if ($NoModifyPath) { Write-Host 'User PATH was not changed.' } else { Write-Host 'User PATH is configured for new terminals.' }
        Write-Host ('For this PowerShell session, run: $env:PATH = ''' + $InstallDir.Replace("'", "''") + ';'' + $env:PATH')
    } catch {
        $failure = $_.Exception.Message
        try {
            if ($pathChanged) { [Environment]::SetEnvironmentVariable('PATH', $oldUserPath, 'User') }
            if ($newPackageMoved) { Remove-Item -LiteralPath $packageDirectory -Recurse -Force }
            if ($newExecutableMoved) { [IO.File]::Delete($executable) }
            if ($oldPackageMoved) { [IO.Directory]::Move($backupPackage, $packageDirectory) }
            if ($oldExecutableMoved) { [IO.File]::Move($backupExecutable, $executable) }
        } catch { throw ('Installation failed: ' + $failure + ' Rollback could not finish. Previous files are retained at ' + $backupExecutable + ' and ' + $backupPackage + '.') }
        throw ('Installation failed; any previous executable and notices were preserved. ' + $failure)
    } finally {
        foreach ($path in @($temporary, $stageExecutableDirectory, $stagePackage)) {
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
