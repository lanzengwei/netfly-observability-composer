<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Trace\SpanIdGenerator;
use Netfly\Observability\Trace\TraceIdGenerator;
use Netfly\Observability\Trace\TracePropagator;
use PHPUnit\Framework\TestCase;

final class TracePropagatorTest extends TestCase
{
    public function test_extracts_custom_netfly_trace_headers(): void
    {
        $propagator = new TracePropagator(new ObservabilityConfig(), new TraceIdGenerator(), new SpanIdGenerator());
        $request = (new ServerRequest('GET', '/orders'))
            ->withHeader('X-Netfly-Trace-Id', 'trace-custom-123')
            ->withHeader('X-Netfly-Span-Id', 'upstream-span-1')
            ->withHeader('X-Netfly-Parent-Span-Id', 'upstream-root');

        $incoming = $propagator->extract($request);

        self::assertSame('trace-custom-123', $incoming->traceId);
        self::assertSame('upstream-span-1', $incoming->parentSpanId);
        self::assertSame('upstream-root', $incoming->remoteParentSpanId);
    }

    public function test_builds_outgoing_headers_from_current_context(): void
    {
        $context = new TraceContext();
        $context->setTraceId('trace-outgoing');
        $context->startSpan('root-span', null, 'GET /orders', 'server');
        $propagator = new TracePropagator(new ObservabilityConfig(), new TraceIdGenerator(), new SpanIdGenerator());

        $headers = $propagator->headers($context);

        self::assertSame('trace-outgoing', $headers['X-Netfly-Trace-Id']);
        self::assertSame('root-span', $headers['X-Netfly-Span-Id']);
        self::assertSame('', $headers['X-Netfly-Parent-Span-Id']);
    }
}
