#!/usr/bin/env php
<?php

ini_set('memory_limit', '4G');

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
