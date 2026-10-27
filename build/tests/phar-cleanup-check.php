<?php

declare(strict_types=1);

// Reproduce native Phar unlink reporting success with a remaining file.
// The production fallback must use PHP's Unicode-aware filesystem operation.
$directory = sys_get_temp_dir() . '/echo-cleanup-check-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Cannot create cleanup fixture.');
}
try {
    $payload = $directory . '/fixture.phar';
    $archive = new Phar($payload);
    $archive['entry.php'] = '<?php fwrite(STDOUT, "fixture"); exit(17);';
    $archive->setStub('<?php __HALT_COMPILER();');
    unset($archive);
    $stub = str_replace(
        ['__ECHO_PAYLOAD_STRATEGY__', '__ECHO_ENTRY__', 'Phar::unlinkArchive($temporary)'],
        ['temporary-phar', 'entry.php', 'simulateNativeUnlink($temporary)'],
        file_get_contents(dirname(__DIR__) . '/phar-stub.php')
    );
    file_put_contents($directory . '/stub.php', $stub);
    $wrapper = '<?php ' . PHP_EOL
        . 'function micro_open_self() { return fopen(' . var_export($payload, true) . ', "rb"); }' . PHP_EOL
        . 'function micro_get_sfxsize() { return 0; }' . PHP_EOL
        . 'function simulateNativeUnlink($path) {' . PHP_EOL
        . '    Phar::unlinkArchive($path);' . PHP_EOL
        . '    file_put_contents($path, "remaining native file");' . PHP_EOL
        . '    return true;' . PHP_EOL
        . '}' . PHP_EOL
        . 'require ' . var_export($directory . '/stub.php', true) . ';';
    file_put_contents($directory . '/wrapper.php', $wrapper);
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $directory . '/wrapper.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $directory,
        array_merge(getenv(), ['TMP' => $directory, 'TEMP' => $directory, 'TMPDIR' => $directory])
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot run cleanup fixture.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 17 || $stdout !== 'fixture' || $stderr !== '') {
        throw new RuntimeException('Cleanup fixture invocation failed: ' . $stdout . $stderr);
    }
    if (glob($directory . '/rarus-echo-payload-*') !== []) {
        throw new RuntimeException('Temporary PHAR remains after native unlink reported success.');
    }
    fwrite(STDOUT, "Temporary PHAR fallback cleanup passed.\n");
} finally {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}
