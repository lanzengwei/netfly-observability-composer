<?php

declare(strict_types=1);

return [
    'dependencies' => [
        App\Http\DemoKernel::class => App\Http\DemoKernel::class,
        App\Service\ObservabilityRuntime::class => App\Service\ObservabilityRuntime::class,
    ],
];
