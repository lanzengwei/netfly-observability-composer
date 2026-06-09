<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Logging\LogType;
use Netfly\Observability\Logging\ObservabilityLogger;
use Netfly\Observability\Metrics\MetricsRegistry;

final class HttpCollector
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly MetricsRegistry $metrics,
        private readonly ObservabilityLogger $logger
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(
        string $traceId,
        string $method,
        string $route,
        int $status,
        float $durationSeconds,
        array $context = []
    ): void {
        if (! $this->config->collectorEnabled('http')) {
            return;
        }

        $labels = [
            'method' => $method,
            'route' => $route,
            'status' => (string) $status,
        ];

        if ($this->config->metricsEnabled()) {
            $this->metrics->counter('netfly_http_requests_total', $labels);
            $this->metrics->histogram('netfly_http_request_duration_seconds', $labels, $durationSeconds);
        }

        $durationMs = round($durationSeconds * 1000, 3);
        $logContext = array_merge($context, [
            'method' => $method,
            'route' => $route,
            'status' => $status,
            'duration_ms' => $durationMs,
        ]);

        $this->logger->log('info', LogType::REQUEST, 'HTTP request completed', $traceId, $logContext);

        $threshold = $this->config->slowThresholdMs('http');
        if ($threshold > 0 && $durationMs >= $threshold) {
            $this->logger->log('warning', LogType::SLOW, 'Slow HTTP request', $traceId, array_merge($logContext, [
                'threshold_ms' => $threshold,
                'component' => 'http',
            ]));
        }
    }
}
