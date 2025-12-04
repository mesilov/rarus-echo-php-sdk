<?php

declare(strict_types=1);

// Bootstrap file for PHPUnit tests

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        putenv($line);
        [$name, $value] = explode('=', $line, 2);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Set timezone
date_default_timezone_set('UTC');
