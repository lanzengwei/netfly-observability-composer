<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Logging\JsonLogFormatter;
use Netfly\Observability\Logging\ObservabilityLogger;
use PHPUnit\Framework\TestCase;

final class ObservabilityLoggerTest extends TestCase
{
    public function test_writes_json_line_when_enabled(): void
    {
        $path = sys_get_temp_dir() . '/netfly-observability-test-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'project' => 'shop',
            'env' => 'local',
            'service' => 'api',
            'logging' => ['enabled' => true, 'path' => $path],
        ]);
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));

        $logger->log('info', 'app', 'hello', 'trace-1', ['a' => 'b']);

        $line = file_get_contents($path);
        self::assertIsString($line);
        self::assertStringContainsString('"trace_id":"trace-1"', $line);

        @unlink($path);
    }

    public function test_app_log_inherits_trace_and_current_span_when_not_explicitly_set(): void
    {
        $path = sys_get_temp_dir() . '/netfly-observability-test-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'project' => 'shop',
            'env' => 'local',
            'service' => 'api',
            'logging' => ['enabled' => true, 'path' => $path],
        ]);
        $traceContext = new TraceContext();
        $traceContext->setTraceId('trace-app');
        $traceContext->startSpan('request-span', null, 'GET /orders', 'server');
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()), $traceContext);

        $logger->log('info', 'app', 'business event', null, ['order_id' => 123]);

        $line = file_get_contents($path);
        self::assertIsString($line);
        $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('trace-app', $data['trace_id']);
        self::assertSame('request-span', $data['span_id']);
        self::assertNull($data['parent_span_id']);
        self::assertSame('GET /orders', $data['span_name']);
        self::assertSame('server', $data['span_kind']);

        @unlink($path);
    }

    public function test_does_not_write_when_logging_disabled(): void
    {
        $path = sys_get_temp_dir() . '/netfly-observability-test-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'logging' => ['enabled' => false, 'path' => $path],
        ]);
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));

        $logger->log('info', 'app', 'hello', 'trace-1');

        self::assertFileDoesNotExist($path);
    }
}
