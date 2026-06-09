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
}
