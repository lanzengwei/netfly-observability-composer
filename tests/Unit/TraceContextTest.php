<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Context\TraceContext;
use PHPUnit\Framework\TestCase;

final class TraceContextTest extends TestCase
{
    public function test_sets_gets_and_clears_trace_id(): void
    {
        $context = new TraceContext();

        $context->setTraceId('trace-1');

        self::assertSame('trace-1', $context->getTraceId());

        $context->clear();

        self::assertNull($context->getTraceId());
    }

    public function test_tracks_current_span_and_creates_child_span(): void
    {
        $context = new TraceContext();

        $root = $context->startSpan('root-span', null, 'GET /orders', 'server');
        $child = $context->createChildSpan('mysql-span', 'mysql select', 'client');

        self::assertSame('root-span', $root->spanId);
        self::assertNull($root->parentSpanId);
        self::assertSame('GET /orders', $root->spanName);
        self::assertSame('server', $root->spanKind);
        self::assertSame('root-span', $context->currentSpan()?->spanId);
        self::assertSame('mysql-span', $child->spanId);
        self::assertSame('root-span', $child->parentSpanId);
        self::assertSame('mysql select', $child->spanName);
        self::assertSame('client', $child->spanKind);

        $context->clear();

        self::assertNull($context->currentSpan());
    }
}
