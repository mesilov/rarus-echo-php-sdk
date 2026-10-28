#!/usr/bin/env php
<?php

ini_set('memory_limit', '4G');

// Capture micro's physical executable before Windows switches to a temporary PHAR.
// This documented helper resolves the native file rather than PHP_BINARY or PHAR __DIR__.
$physicalExecutable = function_exists('micro_get_self_filename')
    ? micro_get_self_filename()
    : realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
if (!is_string($physicalExecutable) || $physicalExecutable === '') {
    throw new RuntimeException('Cannot determine physical CLI executable location.');
}
// The pinned wrappe runner overwrites this value on every cold/cached launch.
$launcher = getenv('RARUS_ECHO_LAUNCHER');
if ('__ECHO_LAUNCHER__' === 'wrappe' && is_string($launcher) && $launcher !== '') {
    $resolved = realpath($launcher);
    if ($launcher[0] !== '/' || $resolved === false || !is_file($resolved)) {
        throw new RuntimeException('Cannot determine packed CLI launcher location.');
    }
    $physicalExecutable = $resolved;
}
putenv('RARUS_ECHO_LAUNCHER');
unset($launcher, $resolved);
define('RARUS_ECHO_EXECUTABLE', $physicalExecutable);
unset($physicalExecutable);

// Windows micro stream hooks are bypassed by loading the payload as a regular PHAR.
if ('__ECHO_PAYLOAD_STRATEGY__' === 'temporary-phar' && function_exists('micro_open_self')) {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rarus-echo-payload-' . bin2hex(random_bytes(16));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create temporary application PHAR.');
    }
    $temporary = $directory . DIRECTORY_SEPARATOR . 'application.phar';
    register_shutdown_function(static function () use ($temporary, $directory): void {
        if (is_file($temporary)) {
            try {
                Phar::unlinkArchive($temporary);
            } catch (Throwable) {
                // The portable filesystem fallback below also handles open archives.
            }
            // Native Phar unlink may report success without deleting a Unicode path.
            clearstatcache(true, $temporary);
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
        @rmdir($directory);
    });
    $source = micro_open_self();
    $destination = fopen($temporary, 'x+b');
    try {
        if ($source === false || $destination === false || fseek($source, micro_get_sfxsize()) !== 0) {
            throw new RuntimeException('Cannot read embedded application PHAR.');
        }
        if (stream_copy_to_stream($source, $destination) === false) {
            throw new RuntimeException('Cannot copy embedded application PHAR.');
        }
    } finally {
        if (is_resource($source)) {
            fclose($source);
        }
        if (is_resource($destination)) {
            fclose($destination);
        }
    }
    require 'phar://' . $temporary . '/__ECHO_ENTRY__';
} else {
    Phar::mapPhar('rarus-echo.phar');
    require 'phar://rarus-echo.phar/__ECHO_ENTRY__';
}
__HALT_COMPILER();
