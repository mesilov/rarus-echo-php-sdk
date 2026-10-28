<?php

declare(strict_types=1);

// Exercise the real bootstrap in child processes so constant/environment state is isolated.
$root = sys_get_temp_dir() . '/echo-launcher-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$launcher = $root . '/launcher copy';
file_put_contents($launcher, 'outer launcher');
$stub = file_get_contents(dirname(__DIR__) . '/phar-stub.php');
$start = strpos($stub, "ini_set(");
$end = strpos($stub, '// Windows micro stream');
$bootstrap = substr($stub, $start, $end - $start);
$cases = [['none', 'invalid inherited path', false]];
if (DIRECTORY_SEPARATOR === '/') {
    $cases = array_merge($cases, [['wrappe', $launcher, false], ['wrappe', 'relative', true], ['wrappe', $root . '/missing', true]]);
}
try {
    foreach ($cases as [$strategy, $path, $mustFail]) {
        $script = $root . '/inner.php';
        file_put_contents($script, '<?php function micro_get_self_filename() { return __FILE__; }' . "\n"
            . str_replace('__ECHO_LAUNCHER__', $strategy, $bootstrap)
            . 'echo json_encode([RARUS_ECHO_EXECUTABLE, getenv("RARUS_ECHO_LAUNCHER")]);');
        $pipes = [];
        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
            $root, array_merge(getenv(), ['RARUS_ECHO_LAUNCHER' => $path]));
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($mustFail) {
            if ($status === 0 || !str_contains($stdout . $stderr, 'packed CLI launcher location')) {
                throw new RuntimeException('Invalid launcher path was accepted.');
            }
        } elseif ($status !== 0 || json_decode($stdout, true) !== [realpath($strategy === 'wrappe' ? $launcher : $script), false]) {
            throw new RuntimeException('Native launcher path or environment cleanup differs: ' . $stdout . $stderr);
        }
    }
    fwrite(STDOUT, "Launcher origin and environment cleanup passed.\n");
} finally {
    @unlink($root . '/inner.php');
    @unlink($launcher);
    @rmdir($root);
}
