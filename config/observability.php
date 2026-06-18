<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('NETFLY_OBSERVABILITY_ENABLED', true),
    'project' => env('NETFLY_OBSERVABILITY_PROJECT', 'default'),
    'env' => env('APP_ENV', 'local'),
    'service' => env('NETFLY_OBSERVABILITY_SERVICE', 'hyperf'),
    'metrics' => [
        'enabled' => (bool) env('NETFLY_OBSERVABILITY_METRICS_ENABLED', true),
        'path' => env('NETFLY_OBSERVABILITY_METRICS_PATH', '/metrics'),
    ],
    'trace' => [
        'headers' => [
            'trace_id' => env('NETFLY_OBSERVABILITY_TRACE_HEADER', 'X-Netfly-Trace-Id'),
            'span_id' => env('NETFLY_OBSERVABILITY_SPAN_HEADER', 'X-Netfly-Span-Id'),
            'parent_span_id' => env('NETFLY_OBSERVABILITY_PARENT_SPAN_HEADER', 'X-Netfly-Parent-Span-Id'),
        ],
    ],
    'logging' => [
        'enabled' => (bool) env('NETFLY_OBSERVABILITY_LOGGING_ENABLED', true),
        'path' => env('NETFLY_OBSERVABILITY_LOG_PATH', BASE_PATH . '/runtime/logs/netfly-observability.log'),
        'remote' => [
            'enabled' => (bool) env('NETFLY_OBSERVABILITY_REMOTE_LOG_ENABLED', false),
            'driver' => env('NETFLY_OBSERVABILITY_REMOTE_LOG_DRIVER', 'tcp'),
            'host' => env('NETFLY_OBSERVABILITY_REMOTE_LOG_HOST', '127.0.0.1'),
            'port' => (int) env('NETFLY_OBSERVABILITY_REMOTE_LOG_PORT', 9000),
            'url' => env('NETFLY_OBSERVABILITY_REMOTE_LOG_URL', 'http://127.0.0.1:9000/logs'),
            'timeout_ms' => (int) env('NETFLY_OBSERVABILITY_REMOTE_LOG_TIMEOUT_MS', 50),
            'headers' => [],
        ],
    ],
    'http' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_HTTP_ENABLED', true)],
    'mysql' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_MYSQL_ENABLED', true)],
    'redis' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_REDIS_ENABLED', true)],
    'rabbitmq' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_RABBITMQ_ENABLED', true)],
    'slow_threshold_ms' => [
        'http' => (int) env('NETFLY_OBSERVABILITY_SLOW_HTTP_MS', 1000),
        'mysql' => (int) env('NETFLY_OBSERVABILITY_SLOW_MYSQL_MS', 200),
        'redis' => (int) env('NETFLY_OBSERVABILITY_SLOW_REDIS_MS', 100),
        'rabbitmq' => (int) env('NETFLY_OBSERVABILITY_SLOW_RABBITMQ_MS', 500),
    ],
];
