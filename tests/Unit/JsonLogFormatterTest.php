<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Logging\JsonLogFormatter;
use PHPUnit\Framework\TestCase;

final class JsonLogFormatterTest extends TestCase
{
    public function test_formats_required_json_fields(): void
    {
        $formatter = new JsonLogFormatter(['project' => 'shop', 'env' => 'local', 'service' => 'api']);

        $json = $formatter->format('info', 'request', 'ok', 'trace-1', ['duration_ms' => 12.3]);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('shop', $data['project']);
        self::assertSame('local', $data['env']);
        self::assertSame('api', $data['service']);
        self::assertSame('trace-1', $data['trace_id']);
        self::assertSame('request', $data['log_type']);
        self::assertSame('ok', $data['message']);
        self::assertSame(12.3, $data['duration_ms']);
        self::assertSame(12.3, $data['context']['duration_ms']);
        self::assertArrayHasKey('timestamp', $data);
    }

    public function test_promotes_common_query_fields_to_top_level(): void
    {
        $formatter = new JsonLogFormatter(['project' => 'shop', 'env' => 'local', 'service' => 'api']);

        $json = $formatter->format('warning', 'slow', 'slow call', 'trace-1', [
            'component' => 'mysql',
            'operation' => 'select',
            'threshold_ms' => 100,
            'exception_class' => 'RuntimeException',
        ]);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('mysql', $data['component']);
        self::assertSame('select', $data['operation']);
        self::assertSame(100, $data['threshold_ms']);
        self::assertSame('RuntimeException', $data['exception_class']);
    }
}
