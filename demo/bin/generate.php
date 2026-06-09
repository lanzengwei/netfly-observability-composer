<?php

declare(strict_types=1);

use App\Service\TrafficService;

require dirname(__DIR__) . '/vendor/autoload.php';

$service = TrafficService::create();

while (true) {
    $service->tick();
    usleep(800000);
}
