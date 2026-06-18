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
use Netfly\Observability\Trace\SpanContext;
use Netfly\Observability\Trace\SpanIdGenerator;
use Netfly\Observability\Trace\TraceIdGenerator;
use Throwable;

final class ObservabilityRuntime
{
    private readonly ObservabilityConfig $config;

    private readonly TraceContext $traceContext;

    private readonly MetricsRegistry $metrics;

    private readonly ObservabilityLogger $logger;

    private readonly HttpCollector $http;

    private readonly MysqlCollector $mysql;

    private readonly RedisCollector $redis;

    private readonly RabbitMqCollector $rabbitmq;

    private readonly RuntimeCollector $runtime;

    private readonly PrometheusRenderer $renderer;

    private readonly TraceIdGenerator $traceIdGenerator;

    private readonly SpanIdGenerator $spanIdGenerator;

    public function __construct()
    {
        $this->config = new ObservabilityConfig([
            'project' => getenv('NETFLY_OBSERVABILITY_PROJECT') ?: 'demo-shop',
            'env' => getenv('APP_ENV') ?: 'local',
            'service' => getenv('NETFLY_OBSERVABILITY_SERVICE') ?: 'demo-api',
            'logging' => [
                'path' => getenv('NETFLY_OBSERVABILITY_LOG_PATH') ?: dirname(__DIR__, 2) . '/runtime/logs/netfly-observability.log',
                'remote' => [
                    'enabled' => filter_var(getenv('NETFLY_OBSERVABILITY_REMOTE_LOG_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
                    'driver' => getenv('NETFLY_OBSERVABILITY_REMOTE_LOG_DRIVER') ?: 'tcp',
                    'host' => getenv('NETFLY_OBSERVABILITY_REMOTE_LOG_HOST') ?: '127.0.0.1',
                    'port' => (int) (getenv('NETFLY_OBSERVABILITY_REMOTE_LOG_PORT') ?: 9000),
                    'url' => getenv('NETFLY_OBSERVABILITY_REMOTE_LOG_URL') ?: 'http://127.0.0.1:9000/logs',
                    'timeout_ms' => (int) (getenv('NETFLY_OBSERVABILITY_REMOTE_LOG_TIMEOUT_MS') ?: 50),
                ],
            ],
            'slow_threshold_ms' => [
                'http' => 120,
                'mysql' => 80,
                'redis' => 60,
                'rabbitmq' => 100,
            ],
        ]);
        $this->traceContext = new TraceContext();
        $this->metrics = new MetricsRegistry($this->config->identityLabels());
        $this->logger = new ObservabilityLogger(
            $this->config,
            new JsonLogFormatter($this->config->identityLabels()),
            $this->traceContext
        );
        $this->http = new HttpCollector($this->config, $this->metrics, $this->logger);
        $this->mysql = new MysqlCollector($this->config, $this->metrics, $this->logger, $this->traceContext);
        $this->redis = new RedisCollector($this->config, $this->metrics, $this->logger, $this->traceContext);
        $this->rabbitmq = new RabbitMqCollector($this->config, $this->metrics, $this->logger, $this->traceContext);
        $this->runtime = new RuntimeCollector($this->config, $this->metrics);
        $this->renderer = new PrometheusRenderer();
        $this->traceIdGenerator = new TraceIdGenerator();
        $this->spanIdGenerator = new SpanIdGenerator();
    }

    /**
     * @param array<string, string> $headers
     */
    public function begin(string $method, string $route, string $scenario, array $headers = []): SpanContext
    {
        $traceId = $this->traceIdGenerator->fromHeader($headers['x-netfly-trace-id'] ?? $headers['x-trace-id'] ?? null);
        $parentSpanId = $headers['x-netfly-span-id'] ?? null;
        $this->traceContext->setTraceId($traceId);
        $this->runtime->collect();

        return $this->traceContext->startSpan(
            $this->spanIdGenerator->generate(),
            is_string($parentSpanId) && $parentSpanId !== '' ? $parentSpanId : null,
            sprintf('%s %s', strtoupper($method), $route),
            'server'
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function finish(string $method, string $route, int $status, float $durationSeconds, array $context): void
    {
        $span = $this->traceContext->currentSpan();
        $this->http->record($this->traceId(), strtoupper($method), $route, $status, $durationSeconds, $context + ($span?->toLogContext() ?? []));
        $this->traceContext->clear();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function app(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, LogType::APP, $message, null, $context);
    }

    /**
     * @param array<string, mixed> $labels
     * @param array<string, mixed> $context
     */
    public function mysql(string $operation, array $labels, float $durationSeconds, string $result, ?string $errorClass, array $context): void
    {
        $this->mysql->record(new DependencyResult('mysql', $operation, $labels, $durationSeconds, $result, $errorClass, $context));
    }

    /**
     * @param array<string, mixed> $labels
     * @param array<string, mixed> $context
     */
    public function redis(string $operation, array $labels, float $durationSeconds, string $result, array $context, ?string $errorClass = null): void
    {
        $this->redis->record(new DependencyResult('redis', $operation, $labels, $durationSeconds, $result, $errorClass, $context));
    }

    /**
     * @param array<string, mixed> $labels
     * @param array<string, mixed> $context
     */
    public function rabbitmq(string $operation, array $labels, float $durationSeconds, string $result, array $context, ?string $errorClass = null): void
    {
        $this->rabbitmq->record(new DependencyResult('rabbitmq', $operation, $labels, $durationSeconds, $result, $errorClass, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(Throwable $throwable, array $context = []): void
    {
        $this->logger->log('error', LogType::ERROR, $throwable->getMessage(), null, $context + [
            'exception_class' => $throwable::class,
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ]);
    }

    public function metrics(): string
    {
        return $this->renderer->render($this->metrics->samples());
    }

    public function traceId(): string
    {
        return $this->traceContext->getTraceId() ?? $this->traceIdGenerator->generate();
    }

    public function spanId(): ?string
    {
        return $this->traceContext->currentSpan()?->spanId;
    }
}
