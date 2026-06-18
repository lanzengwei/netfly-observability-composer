<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Trace\SpanIdGenerator;
use PHPUnit\Framework\TestCase;

final class SpanIdGeneratorTest extends TestCase
{
    public function test_generates_16_hex_character_span_ids(): void
    {
        $generator = new SpanIdGenerator();

        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $generator->generate());
    }

    public function test_validates_inbound_span_ids(): void
    {
        $generator = new SpanIdGenerator();

        self::assertTrue($generator->isValid('span_1234-5678'));
        self::assertFalse($generator->isValid("../bad span\n"));
        self::assertFalse($generator->isValid('abc'));
    }
}
