<?php

declare(strict_types=1);

namespace Netfly\Observability;

use Netfly\Observability\Collector\HttpCollector;
use Netfly\Observability\Collector\MysqlCollector;
use Netfly\Observability\Collector\RabbitMqCollector;
use Netfly\Observability\Collector\RedisCollector;
use Netfly\Observability\Collector\RuntimeCollector;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Controller\MetricsController;
use Netfly\Observability\Logging\JsonLogFormatter;
use Netfly\Observability\Logging\LogSanitizer;
use Netfly\Observability\Logging\ObservabilityLogger;
use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;
use Netfly\Observability\Middleware\TraceMiddleware;
use Netfly\Observability\Trace\TraceIdGenerator;

final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                ObservabilityConfig::class => ObservabilityConfig::class,
                TraceIdGenerator::class => TraceIdGenerator::class,
                TraceContext::class => TraceContext::class,
                LogSanitizer::class => LogSanitizer::class,
                JsonLogFormatter::class => JsonLogFormatter::class,
                ObservabilityLogger::class => ObservabilityLogger::class,
                MetricsRegistry::class => MetricsRegistry::class,
                PrometheusRenderer::class => PrometheusRenderer::class,
                HttpCollector::class => HttpCollector::class,
                MysqlCollector::class => MysqlCollector::class,
                RedisCollector::class => RedisCollector::class,
                RabbitMqCollector::class => RabbitMqCollector::class,
                RuntimeCollector::class => RuntimeCollector::class,
                TraceMiddleware::class => TraceMiddleware::class,
                MetricsController::class => MetricsController::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [__DIR__],
                ],
            ],
            'publish' => [
                [
                    'id' => 'observability',
                    'description' => 'Netfly observability config.',
                    'source' => __DIR__ . '/../config/observability.php',
                    'destination' => (defined('BASE_PATH') ? constant('BASE_PATH') : getcwd()) . '/config/autoload/observability.php',
                ],
            ],
        ];
    }
}
