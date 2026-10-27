<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$info = json_decode(file_get_contents($root . '/build-info.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$extensions = [...$info['extensions'], 'json', 'fileinfo'];
foreach ($lock['packages'] as $package) {
    foreach (array_keys($package['require'] ?? []) as $name) {
        if (str_starts_with($name, 'ext-')) {
            $extensions[] = substr($name, 4);
        }
    }
}
foreach (array_unique($extensions) as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException('Missing production extension: ' . $extension);
    }
}
Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
Http\Discovery\Psr17FactoryDiscovery::findResponseFactory();
Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();
Http\Discovery\Psr18ClientDiscovery::find();
if (!in_array('https', curl_version()['protocols'], true)
    || !in_array('https', stream_get_wrappers(), true)
    || mb_strtoupper('Привет') !== 'ПРИВЕТ'
    || !class_exists(Rarus\Echo\Core\ApiClient::class)
) {
    throw new RuntimeException('Runtime transport, Unicode or SDK autoload check failed.');
}
// Probe-only active-process timeout/cancellation acceptance; no public CLI option.
if (in_array($argv[1] ?? null, ['media-cancel-fixture', 'media-timeout-fixture'], true)) {
    $timeoutFixture = $argv[1] === 'media-timeout-fixture';
    $activeBeforeDeadline = false;
    $started = microtime(true);
    $deadline = $started + ($timeoutFixture ? 6 : 20);
    $runner = new Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner();
    $cancelled = false;
    $handler = static function () use ($runner, &$cancelled): void {
        $cancelled = true;
        $runner->cancel();
    };
    if (!$timeoutFixture && PHP_OS_FAMILY === 'Windows') {
        if (!function_exists('sapi_windows_set_ctrl_handler') || !sapi_windows_set_ctrl_handler($handler)) {
            throw new RuntimeException('Native Windows console-control handler cannot be registered.');
        }
    } elseif (!$timeoutFixture) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $handler);
    }
    $owned = $argv[4];
    if (!mkdir($owned, 0o700)) {
        throw new RuntimeException('Cannot create native media fixture directory.');
    }
    $output = $owned . DIRECTORY_SEPARATOR . 'partial.flac';
    try {
        $runner->run([$argv[2], '-nostdin', '-v', 'error', '-re', '-stream_loop', '-1',
            '-i', $argv[3], '-vn', '-c:a', 'flac', '-flush_packets', '1', $output], $deadline,
            monitor: static function () use ($output, $argv, $deadline, &$activeBeforeDeadline): void {
                clearstatcache(true, $output);
                if (is_file($output) && filesize($output) > 0 && microtime(true) < $deadline) {
                    $activeBeforeDeadline = true;
                    file_put_contents($argv[5], 'ready');
                }
            });
        throw new RuntimeException('Native media fixture completed without reaching its timeout/cancellation.');
    } catch (RuntimeException $exception) {
        $expected = $timeoutFixture ? 'timeout' : 'cancelled';
        if (!$activeBeforeDeadline || (!$timeoutFixture && !$cancelled) || !str_contains($exception->getMessage(), $expected)) {
            throw $exception;
        }
    } finally {
        $runner->cancel();
        @unlink($output);
        @rmdir($owned);
        if (!$timeoutFixture && PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler($handler, false);
        } elseif (!$timeoutFixture) {
            pcntl_signal(SIGINT, SIG_DFL);
        }
    }
    $result = $timeoutFixture
        ? ['timed_out' => true, 'active_before_deadline' => $activeBeforeDeadline, 'elapsed_seconds' => microtime(true) - $started]
        : ['cancelled' => true];
    echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}
// Probe-only scripted dialogue: the shipped CLI has no unattended secret-input switch.
if (($argv[1] ?? null) === 'configure-fixture') {
    $application = Rarus\Echo\Infrastructure\Console\ApplicationFactory::create();
    $application->setAutoExit(false);
    $helper = new class ($argv[3]) extends Symfony\Component\Console\Helper\QuestionHelper {
        private array $answers;

        public function __construct(string $url)
        {
            $this->answers = ['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', $url];
        }

        public function ask(
            Symfony\Component\Console\Input\InputInterface $input,
            Symfony\Component\Console\Output\OutputInterface $output,
            Symfony\Component\Console\Question\Question $question
        ): mixed {
            if ($question instanceof Symfony\Component\Console\Question\ConfirmationQuestion) {
                return true;
            }
            if (count($this->answers) === 3 && (!$question->isHidden() || $question->isHiddenFallback())) {
                throw new RuntimeException('API key input must remain hidden.');
            }

            return array_shift($this->answers);
        }
    };
    $application->find('install')->setHelperSet(new Symfony\Component\Console\Helper\HelperSet([$helper]));
    $arguments = ['command' => 'install', '--no-ansi' => true];
    if ($argv[2] !== 'default') {
        $arguments['--config'] = $argv[2];
    }
    $input = new Symfony\Component\Console\Input\ArrayInput($arguments);
    $input->setInteractive(true);
    exit($application->run($input));
}
echo json_encode([
    'php_version' => PHP_VERSION,
    'native_executable' => defined('RARUS_ECHO_EXECUTABLE') ? RARUS_ECHO_EXECUTABLE : null,
    'windows_ctrl_handler' => function_exists('sapi_windows_set_ctrl_handler'),
    'version' => Rarus\Echo\Infrastructure\Console\BuildVersion::get(),
    'extensions' => array_values(array_unique($extensions)),
    'lock_sha256' => hash_file('sha256', $root . '/composer.lock'),
], JSON_THROW_ON_ERROR) . PHP_EOL;
