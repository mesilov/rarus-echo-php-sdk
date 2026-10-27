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
// Probe-only native cancellation acceptance: no additional user-facing CLI option.
if (($argv[1] ?? null) === 'media-cancel-fixture') {
    $runner = new Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner();
    $cancelled = false;
    $handler = static function () use ($runner, &$cancelled): void {
        $cancelled = true;
        $runner->cancel();
    };
    if (PHP_OS_FAMILY === 'Windows') {
        if (!function_exists('sapi_windows_set_ctrl_handler') || !sapi_windows_set_ctrl_handler($handler)) {
            throw new RuntimeException('Native Windows console-control handler cannot be registered.');
        }
    } else {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $handler);
    }
    $owned = $argv[4];
    if (!mkdir($owned, 0o700)) {
        throw new RuntimeException('Cannot create cancellation fixture directory.');
    }
    $output = $owned . DIRECTORY_SEPARATOR . 'partial.flac';
    try {
        $runner->run([$argv[2], '-nostdin', '-v', 'error', '-re', '-stream_loop', '-1',
            '-i', $argv[3], '-vn', '-c:a', 'flac', '-flush_packets', '1', $output], microtime(true) + 20,
            monitor: static function () use ($output, $argv): void {
                clearstatcache(true, $output);
                if (is_file($output) && filesize($output) > 0) {
                    file_put_contents($argv[5], 'ready');
                }
            });
        throw new RuntimeException('Cancellation fixture completed without cancellation.');
    } catch (RuntimeException $exception) {
        if (!$cancelled || !str_contains($exception->getMessage(), 'cancelled')) {
            throw $exception;
        }
    } finally {
        $runner->cancel();
        @unlink($output);
        @rmdir($owned);
        if (PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler($handler, false);
        } else {
            pcntl_signal(SIGINT, SIG_DFL);
        }
    }
    echo json_encode(['cancelled' => true], JSON_THROW_ON_ERROR) . PHP_EOL;
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
