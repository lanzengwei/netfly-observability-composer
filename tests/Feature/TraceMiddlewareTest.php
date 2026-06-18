<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Netfly\Observability\Collector\HttpCollector;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Logging\JsonLogFormatter;
use Netfly\Observability\Logging\ObservabilityLogger;
use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;
use Netfly\Observability\Middleware\TraceMiddleware;
use Netfly\Observability\Trace\TraceIdGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TraceMiddlewareTest extends TestCase
{
    public function test_adds_trace_header_and_records_http_metric_and_log(): void
    {
        $logPath = sys_get_temp_dir() . '/netfly-http-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'project' => 'shop',
            'env' => 'test',
            'service' => 'api',
            'logging' => ['path' => $logPath],
        ]);
        $metrics = new MetricsRegistry($config->identityLabels());
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));
        $collector = new HttpCollector($config, $metrics, $logger);
        $middleware = new TraceMiddleware($config, new TraceIdGenerator(), new TraceContext(), $collector);

        $response = $middleware->process(
            (new ServerRequest('GET', '/orders/1'))->withHeader('X-Trace-Id', 'trace-123456'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(201);
                }
            }
        );

        $metricsText = (new PrometheusRenderer())->render($metrics->samples());
        $log = (string) file_get_contents($logPath);

        self::assertSame('trace-123456', $response->getHeaderLine('X-Trace-Id'));
        self::assertStringContainsString('netfly_http_requests_total', $metricsText);
        self::assertStringContainsString('status="201"', $metricsText);
        self::assertStringContainsString('"trace_id":"trace-123456"', $log);
        self::assertStringContainsString('"log_type":"request"', $log);

        @unlink($logPath);
    }

    public function test_custom_trace_headers_create_root_span_in_response_and_log(): void
    {
        $logPath = sys_get_temp_dir() . '/netfly-http-span-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'project' => 'shop',
            'env' => 'test',
            'service' => 'api',
            'logging' => ['path' => $logPath],
        ]);
        $metrics = new MetricsRegistry($config->identityLabels());
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));
        $traceContext = new TraceContext();
        $collector = new HttpCollector($config, $metrics, $logger);
        $middleware = new TraceMiddleware($config, new TraceIdGenerator(), $traceContext, $collector);

        $response = $middleware->process(
            (new ServerRequest('GET', '/orders/1'))
                ->withHeader('X-Netfly-Trace-Id', 'trace-custom-123')
                ->withHeader('X-Netfly-Span-Id', 'upstream-span-1'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200);
                }
            }
        );

        $log = (string) file_get_contents($logPath);
        $data = json_decode(trim($log), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('trace-custom-123', $response->getHeaderLine('X-Netfly-Trace-Id'));
        self::assertNotSame('', $response->getHeaderLine('X-Netfly-Span-Id'));
        self::assertSame('upstream-span-1', $response->getHeaderLine('X-Netfly-Parent-Span-Id'));
        self::assertSame('trace-custom-123', $data['trace_id']);
        self::assertSame($response->getHeaderLine('X-Netfly-Span-Id'), $data['span_id']);
        self::assertSame('upstream-span-1', $data['parent_span_id']);
        self::assertSame('GET /orders/1', $data['span_name']);
        self::assertSame('server', $data['span_kind']);

        @unlink($logPath);
    }

    public function test_disabled_http_collector_only_propagates_trace_header(): void
    {
        $config = new ObservabilityConfig([
            'http' => ['enabled' => false],
        ]);
        $metrics = new MetricsRegistry($config->identityLabels());
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));
        $collector = new HttpCollector($config, $metrics, $logger);
        $middleware = new TraceMiddleware($config, new TraceIdGenerator(), new TraceContext(), $collector);

        $response = $middleware->process(
            new ServerRequest('GET', '/orders/1'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200);
                }
            }
        );

        self::assertNotSame('', $response->getHeaderLine('X-Trace-Id'));
        self::assertSame([], $metrics->samples());
    }
}
