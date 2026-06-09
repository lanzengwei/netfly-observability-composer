<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;
use PHPUnit\Framework\TestCase;

final class MetricsRegistryTest extends TestCase
{
    public function test_counter_is_rendered_with_common_labels(): void
    {
        $registry = new MetricsRegistry(['project' => 'shop', 'env' => 'local', 'service' => 'api']);

        $registry->counter('netfly_http_requests_total', ['method' => 'GET', 'status' => '200'], 1);
        $registry->counter('netfly_http_requests_total', ['method' => 'GET', 'status' => '200'], 2);

        $output = (new PrometheusRenderer())->render($registry->samples());

        self::assertStringContainsString(
            'netfly_http_requests_total{env="local",method="GET",project="shop",service="api",status="200"} 3',
            $output
        );
    }

    public function test_histogram_records_buckets_count_and_sum(): void
    {
        $registry = new MetricsRegistry(['project' => 'shop', 'env' => 'local', 'service' => 'api']);

        $registry->histogram('netfly_http_request_duration_seconds', ['route' => '/orders'], 0.2, [0.1, 0.5]);

        $output = (new PrometheusRenderer())->render($registry->samples());

        self::assertStringContainsString('netfly_http_request_duration_seconds_bucket{env="local",le="0.1",project="shop",route="/orders",service="api"} 0', $output);
        self::assertStringContainsString('netfly_http_request_duration_seconds_bucket{env="local",le="0.5",project="shop",route="/orders",service="api"} 1', $output);
        self::assertStringContainsString('netfly_http_request_duration_seconds_bucket{env="local",le="+Inf",project="shop",route="/orders",service="api"} 1', $output);
        self::assertStringContainsString('netfly_http_request_duration_seconds_count{env="local",project="shop",route="/orders",service="api"} 1', $output);
        self::assertStringContainsString('netfly_http_request_duration_seconds_sum{env="local",project="shop",route="/orders",service="api"} 0.2', $output);
    }
}
