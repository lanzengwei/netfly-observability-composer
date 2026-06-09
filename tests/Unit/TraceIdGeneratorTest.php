<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Trace\TraceIdGenerator;
use PHPUnit\Framework\TestCase;

final class TraceIdGeneratorTest extends TestCase
{
    public function test_uses_valid_inbound_trace_id(): void
    {
        $generator = new TraceIdGenerator();

        self::assertSame('abc123def456', $generator->fromHeader('abc123def456'));
        self::assertSame('trace.id_123-456', $generator->fromHeader('trace.id_123-456'));
    }

    public function test_generates_new_id_for_invalid_header(): void
    {
        $generator = new TraceIdGenerator();

        $traceId = $generator->fromHeader("../bad header\n");

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $traceId);
    }

    public function test_generated_id_is_32_hex_characters(): void
    {
        $generator = new TraceIdGenerator();

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $generator->generate());
    }
}
