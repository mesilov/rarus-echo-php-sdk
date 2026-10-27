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
echo json_encode([
    'php_version' => PHP_VERSION,
    'version' => Rarus\Echo\Infrastructure\Console\BuildVersion::get(),
    'extensions' => array_values(array_unique($extensions)),
    'lock_sha256' => hash_file('sha256', $root . '/composer.lock'),
], JSON_THROW_ON_ERROR) . PHP_EOL;
