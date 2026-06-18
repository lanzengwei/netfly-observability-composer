<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Collector\DependencyResult;
use Netfly\Observability\Collector\MysqlCollector;
use Netfly\Observability\Collector\RabbitMqCollector;
use Netfly\Observability\Collector\RedisCollector;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Logging\JsonLogFormatter;
use Netfly\Observability\Logging\ObservabilityLogger;
use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;
use PHPUnit\Framework\TestCase;

final class DependencyCollectorTest extends TestCase
{
    public function test_mysql_collector_records_success_metric_and_slow_log(): void
    {
        [$config, $metrics, $logger, $trace, $path] = $this->dependencies(['slow_threshold_ms' => ['mysql' => 10]]);
        $trace->setTraceId('trace-db');
        $collector = new MysqlCollector($config, $metrics, $logger, $trace);

        $collector->record(new DependencyResult('mysql', 'select', ['connection' => 'default', 'database' => 'demo'], 0.02, 'success'));

        $text = (new PrometheusRenderer())->render($metrics->samples());
        $log = (string) file_get_contents($path);

        self::assertStringContainsString('netfly_db_queries_total', $text);
        self::assertStringContainsString('operation="select"', $text);
        self::assertStringContainsString('"trace_id":"trace-db"', $log);
        self::assertStringContainsString('"log_type":"db"', $log);
        self::assertStringContainsString('"log_type":"slow"', $log);
    }

    public function test_dependency_logs_are_child_spans_of_current_request_span(): void
    {
        [$config, $metrics, $logger, $trace, $path] = $this->dependencies();
        $trace->setTraceId('trace-db');
        $trace->startSpan('request-span', null, 'GET /orders', 'server');
        $collector = new MysqlCollector($config, $metrics, $logger, $trace);

        $collector->record(new DependencyResult('mysql', 'select', ['connection' => 'default', 'database' => 'demo'], 0.02, 'success'));

        $line = strtok((string) file_get_contents($path), "\n");
        self::assertIsString($line);
        $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('trace-db', $data['trace_id']);
        self::assertNotSame('', $data['span_id']);
        self::assertNotSame('request-span', $data['span_id']);
        self::assertSame('request-span', $data['parent_span_id']);
        self::assertSame('mysql select', $data['span_name']);
        self::assertSame('client', $data['span_kind']);
    }

    public function test_slow_dependency_log_reuses_dependency_span(): void
    {
        [$config, $metrics, $logger, $trace, $path] = $this->dependencies(['slow_threshold_ms' => ['mysql' => 10]]);
        $trace->setTraceId('trace-db');
        $trace->startSpan('request-span', null, 'GET /orders', 'server');
        $collector = new MysqlCollector($config, $metrics, $logger, $trace);

        $collector->record(new DependencyResult('mysql', 'select', ['connection' => 'default', 'database' => 'demo'], 0.02, 'success'));

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($path))));
        self::assertCount(2, $lines);
        $dependency = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        $slow = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('db', $dependency['log_type']);
        self::assertSame('slow', $slow['log_type']);
        self::assertSame($dependency['span_id'], $slow['span_id']);
        self::assertSame('request-span', $slow['parent_span_id']);
    }

    public function test_redis_collector_records_error_result(): void
    {
        [$config, $metrics, $logger, $trace] = $this->dependencies();
        $trace->setTraceId('trace-redis');
        $collector = new RedisCollector($config, $metrics, $logger, $trace);

        $collector->record(new DependencyResult('redis', 'GET', ['pool' => 'default'], 0.01, 'error', 'RuntimeException'));

        $text = (new PrometheusRenderer())->render($metrics->samples());

        self::assertStringContainsString('netfly_redis_commands_total', $text);
        self::assertStringContainsString('command="GET"', $text);
        self::assertStringContainsString('result="error"', $text);
    }

    public function test_rabbitmq_collector_records_publish_metric(): void
    {
        [$config, $metrics, $logger, $trace] = $this->dependencies();
        $trace->setTraceId('trace-amqp');
        $collector = new RabbitMqCollector($config, $metrics, $logger, $trace);

        $collector->record(new DependencyResult('rabbitmq', 'publish', ['exchange' => 'orders', 'queue' => 'order.created'], 0.01, 'success'));

        $text = (new PrometheusRenderer())->render($metrics->samples());

        self::assertStringContainsString('netfly_amqp_publish_total', $text);
        self::assertStringContainsString('exchange="orders"', $text);
        self::assertStringContainsString('queue="order.created"', $text);
    }

    public function test_disabled_collector_does_not_record(): void
    {
        [$config, $metrics, $logger, $trace] = $this->dependencies(['mysql' => ['enabled' => false]]);
        $collector = new MysqlCollector($config, $metrics, $logger, $trace);

        $collector->record(new DependencyResult('mysql', 'select', [], 0.02, 'success'));

        self::assertSame([], $metrics->samples());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{ObservabilityConfig, MetricsRegistry, ObservabilityLogger, TraceContext, string}
     */
    private function dependencies(array $overrides = []): array
    {
        $path = sys_get_temp_dir() . '/netfly-dependency-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig(array_replace_recursive([
            'project' => 'shop',
            'env' => 'test',
            'service' => 'api',
            'logging' => ['path' => $path],
        ], $overrides));
        $metrics = new MetricsRegistry($config->identityLabels());
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));

        return [$config, $metrics, $logger, new TraceContext(), $path];
    }
}
