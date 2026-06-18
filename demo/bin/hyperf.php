#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Http\DemoKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

if (! defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (($argv[1] ?? null) !== 'start') {
    fwrite(STDERR, "Usage: php bin/hyperf.php start\n");
    exit(1);
}

if (! extension_loaded('swoole')) {
    fwrite(STDERR, "The demo requires ext-swoole to run the Hyperf-style HTTP server.\n");
    exit(1);
}

$kernel = new DemoKernel();
$server = new Swoole\Http\Server('0.0.0.0', 9501);
$server->set([
    'worker_num' => 1,
    'enable_coroutine' => true,
    'log_file' => '/tmp/netfly-demo-swoole.log',
]);

$server->on('request', static function ($request, $response) use ($kernel): void {
    $method = (string) ($request->server['request_method'] ?? 'GET');
    $path = parse_url((string) ($request->server['request_uri'] ?? '/'), PHP_URL_PATH) ?: '/';
    $headers = [];
    foreach (($request->header ?? []) as $name => $value) {
        $headers[strtolower((string) $name)] = (string) $value;
    }

    $result = $kernel->dispatch($method, $path, $headers);
    $response->status($result['status']);
    foreach ($result['headers'] as $name => $value) {
        if ($value !== '') {
            $response->header($name, $value);
        }
    }
    $response->end($result['body']);
});

echo "Netfly Hyperf demo server listening on 0.0.0.0:9501\n";
$server->start();
