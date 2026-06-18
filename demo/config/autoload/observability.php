<?php

declare(strict_types=1);

return [
    'enabled' => (bool) (getenv('NETFLY_OBSERVABILITY_ENABLED') ?: true),
    'project' => getenv('NETFLY_OBSERVABILITY_PROJECT') ?: 'demo-shop',
    'env' => getenv('APP_ENV') ?: 'local',
    'service' => getenv('NETFLY_OBSERVABILITY_SERVICE') ?: 'demo-api',
    'logging' => [
        'enabled' => true,
        'path' => getenv('NETFLY_OBSERVABILITY_LOG_PATH') ?: BASE_PATH . '/runtime/logs/netfly-observability.log',
    ],
    'metrics' => [
        'enabled' => true,
        'path' => '/metrics',
    ],
];
