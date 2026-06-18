<?php

declare(strict_types=1);

return [
    'mode' => SWOOLE_PROCESS,
    'servers' => [
        [
            'name' => 'http',
            'type' => 'http',
            'host' => '0.0.0.0',
            'port' => 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [],
        ],
    ],
    'settings' => [
        'worker_num' => 1,
        'enable_coroutine' => true,
    ],
];
