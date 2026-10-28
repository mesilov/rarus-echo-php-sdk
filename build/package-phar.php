<?php

/**
 * Packaging approach adapted from yiipress/engine, commit
 * e61459fc788a74b91188d5d41a5fca59a02a229e. See build/PACKAGING-NOTICES.md.
 */

declare(strict_types=1);

if ($argc !== 4 || !in_array($argv[3], ['application', 'probe'], true)) {
    fwrite(STDERR, "Usage: package-phar.php STAGING TARGET application|probe\n");
    exit(2);
}

$root = realpath($argv[1]);
if ($root === false || !is_file($root . '/vendor/autoload.php')) {
    throw new RuntimeException('Production staging/autoload is missing.');
}

$lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$installed = json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
$expected = array_column($lock['packages'], 'name');
$actual = array_column($installed['packages'], 'name');
sort($expected);
sort($actual);
if ($expected !== $actual || $installed['dev'] !== false) {
    throw new RuntimeException('Staging must contain exactly the locked production packages.');
}

$target = $argv[2];
if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
    throw new RuntimeException('Cannot create output directory.');
}
if (file_exists($target)) {
    unlink($target);
}

$phar = new Phar($target);
$phar->startBuffering();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($iterator as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('Symlinks are not allowed in production staging.');
    }
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('~^vendor/[^/]+/[^/]+/(tests?|\.github)(/|$)~', $relative) === 1) {
        continue;
    }
    if (preg_match('~(^|/)(\.git|\.env(?:\..*)?|\.DS_Store)(/|$)~', $relative) === 1) {
        if (str_starts_with($relative, 'vendor/')) {
            continue;
        }
        throw new RuntimeException('Local state is not allowed in the package: ' . $relative);
    }
    $files[$relative] = $file->getPathname();
}

$entry = 'bin/rarus-echo';
if ($argv[3] === 'probe') {
    $files['packaging/runtime-probe.php'] = __DIR__ . '/tests/runtime-probe.php';
    $entry = 'packaging/runtime-probe.php';
}
$phar->buildFromIterator(new ArrayIterator($files));
$strategy = getenv('ECHO_PAYLOAD_STRATEGY') ?: 'in-place';
if (!in_array($strategy, ['in-place', 'temporary-phar'], true)) {
    throw new RuntimeException('Unsupported PHAR payload strategy.');
}
$stub = str_replace(
    ['__ECHO_ENTRY__', '__ECHO_PAYLOAD_STRATEGY__', '__ECHO_LAUNCHER__'],
    [$entry, $strategy, getenv('ECHO_LAUNCHER') ?: 'none'],
    file_get_contents(__DIR__ . '/phar-stub.php')
);
$phar->setStub($stub);
$phar->setSignatureAlgorithm(Phar::SHA256);
$compression = getenv('ECHO_PHAR_COMPRESSION') ?: 'gzip';
if (!in_array($compression, ['gzip', 'none'], true)) {
    throw new RuntimeException('Unsupported PHAR compression.');
}
fwrite(STDOUT, 'Packing ' . count($files) . ' production files: ' . $compression . PHP_EOL);
if ($compression === 'gzip') {
    $phar->compressFiles(Phar::GZ);
}
$phar->stopBuffering();
chmod($target, 0755);

$archive = new Phar($target);
foreach (['bin/rarus-echo', 'vendor/autoload.php', 'src/Infrastructure/Console/BuildVersion.php', 'LICENSE', 'build-info.json'] as $path) {
    if (!isset($archive[$path])) {
        throw new RuntimeException('Required file is absent: ' . $path);
    }
}
fwrite(STDOUT, 'Built ' . $target . PHP_EOL);
