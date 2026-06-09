<?php

declare(strict_types=1);

namespace App\Service;

use Netfly\Observability\Collector\DependencyResult;
use Netfly\Observability\Collector\HttpCollector;
use Netfly\Observability\Collector\MysqlCollector;
use Netfly\Observability\Collector\RabbitMqCollector;
use Netfly\Observability\Collector\RedisCollector;
use Netfly\Observability\Collector\RuntimeCollector;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Logging\JsonLogFormatter;
use Netfly\Observability\Logging\LogType;
use Netfly\Observability\Logging\ObservabilityLogger;
use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;
use Netfly\Observability\Trace\TraceIdGenerator;
use Throwable;

final class TrafficService
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly TraceContext $traceContext,
        private readonly MetricsRegistry $metrics,
        private readonly ObservabilityLogger $logger,
        private readonly HttpCollector $http,
        private readonly MysqlCollector $mysql,
        private readonly RedisCollector $redis,
        private readonly RabbitMqCollector $rabbitmq,
        private readonly RuntimeCollector $runtime,
        private readonly PrometheusRenderer $renderer,
        private readonly TraceIdGenerator $traceIdGenerator
    ) {
    }

    public static function create(): self
    {
        $config = new ObservabilityConfig([
            'project' => getenv('NETFLY_OBSERVABILITY_PROJECT') ?: 'demo-shop',
            'env' => getenv('APP_ENV') ?: 'local',
            'service' => getenv('NETFLY_OBSERVABILITY_SERVICE') ?: 'demo-api',
            'logging' => [
                'path' => getenv('NETFLY_OBSERVABILITY_LOG_PATH') ?: dirname(__DIR__, 2) . '/runtime/logs/netfly-observability.log',
            ],
            'slow_threshold_ms' => [
                'http' => 120,
                'mysql' => 80,
                'redis' => 60,
                'rabbitmq' => 100,
            ],
        ]);
        $traceContext = new TraceContext();
        $metrics = new MetricsRegistry($config->identityLabels());
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));

        return new self(
            $config,
            $traceContext,
            $metrics,
            $logger,
            new HttpCollector($config, $metrics, $logger),
            new MysqlCollector($config, $metrics, $logger, $traceContext),
            new RedisCollector($config, $metrics, $logger, $traceContext),
            new RabbitMqCollector($config, $metrics, $logger, $traceContext),
            new RuntimeCollector($config, $metrics),
            new PrometheusRenderer(),
            new TraceIdGenerator()
        );
    }

    public function tick(): void
    {
        $traceId = $this->traceIdGenerator->generate();
        $this->traceContext->setTraceId($traceId);
        $this->runtime->collect();

        $route = $this->pick(['/demo/orders', '/demo/cache', '/demo/publish', '/demo/slow']);
        $duration = random_int(20, 220) / 1000;
        $status = random_int(1, 20) === 1 ? 500 : 200;

        $this->http->record($traceId, 'GET', $route, $status, $duration, [
            'uri' => $route,
            'demo' => true,
        ]);

        $this->mysql->record(new DependencyResult('mysql', $this->pick(['select', 'insert', 'update']), [
            'connection' => 'default',
            'database' => 'demo',
        ], random_int(10, 180) / 1000, $status === 500 ? 'error' : 'success', $status === 500 ? 'DemoException' : null, [
            'table' => 'orders',
        ]));

        $this->redis->record(new DependencyResult('redis', $this->pick(['GET', 'SET', 'HGETALL']), [
            'pool' => 'default',
        ], random_int(5, 120) / 1000));

        $this->rabbitmq->record(new DependencyResult('rabbitmq', $this->pick(['publish', 'consume']), [
            'exchange' => 'orders',
            'queue' => 'order.created',
            'routing_key' => 'order.created',
        ], random_int(20, 160) / 1000));

        if ($status === 500) {
            $this->logger->log('error', LogType::ERROR, 'Controlled demo exception', $traceId, [
                'exception_class' => 'DemoException',
                'file' => __FILE__,
                'line' => __LINE__,
            ]);
        }

        $this->traceContext->clear();
    }

    public function slow(): void
    {
        $traceId = $this->traceIdGenerator->generate();
        $this->traceContext->setTraceId($traceId);
        $this->http->record($traceId, 'GET', '/demo/slow', 200, 0.25, ['demo' => true]);
        $this->traceContext->clear();
    }

    public function error(): void
    {
        $traceId = $this->traceIdGenerator->generate();
        $this->logger->log('error', LogType::ERROR, 'Controlled demo error endpoint', $traceId, [
            'exception_class' => 'DemoEndpointException',
        ]);
    }

    public function metrics(): string
    {
        return $this->renderer->render($this->metrics->samples());
    }

    /**
     * @param non-empty-list<string> $values
     */
    private function pick(array $values): string
    {
        return $values[array_rand($values)];
    }
}
