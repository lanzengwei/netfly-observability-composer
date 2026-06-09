<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Feature;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Controller\MetricsController;
use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;
use PHPUnit\Framework\TestCase;

final class MetricsControllerTest extends TestCase
{
    public function test_renders_prometheus_metrics_when_enabled(): void
    {
        $config = new ObservabilityConfig(['project' => 'shop']);
        $metrics = new MetricsRegistry($config->identityLabels());
        $metrics->counter('netfly_http_requests_total', ['status' => '200']);
        $controller = new MetricsController($config, $metrics, new PrometheusRenderer());

        $body = $controller->text();

        self::assertStringContainsString('netfly_http_requests_total', $body);
        self::assertStringContainsString('project="shop"', $body);
    }

    public function test_returns_empty_body_when_metrics_disabled(): void
    {
        $config = new ObservabilityConfig(['metrics' => ['enabled' => false]]);
        $metrics = new MetricsRegistry($config->identityLabels());
        $metrics->counter('netfly_http_requests_total', ['status' => '200']);
        $controller = new MetricsController($config, $metrics, new PrometheusRenderer());

        self::assertSame('', $controller->text());
    }
}
