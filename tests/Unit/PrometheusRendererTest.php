<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Metrics\MetricSample;
use Netfly\Observability\Metrics\PrometheusRenderer;
use PHPUnit\Framework\TestCase;

final class PrometheusRendererTest extends TestCase
{
    public function test_escapes_label_values_and_renders_type_line(): void
    {
        $renderer = new PrometheusRenderer();

        $output = $renderer->render([
            new MetricSample('netfly_test_total', 'counter', ['path' => "/a\nb", 'quote' => '"x"'], 1),
        ]);

        self::assertStringContainsString("# TYPE netfly_test_total counter\n", $output);
        self::assertStringContainsString('path="/a\nb"', $output);
        self::assertStringContainsString('quote="\"x\""', $output);
        self::assertStringEndsWith("\n", $output);
    }
}
