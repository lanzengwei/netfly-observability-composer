<?php

declare(strict_types=1);

use App\Service\TrafficService;

require dirname(__DIR__) . '/vendor/autoload.php';

$service = TrafficService::create();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/metrics') {
    $service->tick();
    header('Content-Type: text/plain; version=0.0.4');
    echo $service->metrics();
    return;
}

if ($path === '/demo/error') {
    $service->error();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'controlled demo error'], JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/demo/slow') {
    usleep(250000);
    $service->slow();
    echo json_encode(['ok' => true, 'type' => 'slow'], JSON_THROW_ON_ERROR);
    return;
}

$service->tick();
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'project' => getenv('NETFLY_OBSERVABILITY_PROJECT') ?: 'demo-shop',
    'endpoints' => ['/metrics', '/demo/error', '/demo/slow'],
], JSON_THROW_ON_ERROR);
