<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Metrics\MetricsRegistry;

final class RuntimeCollector
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly MetricsRegistry $metrics
    ) {
    }

    public function collect(): void
    {
        if (! $this->config->enabled() || ! $this->config->metricsEnabled()) {
            return;
        }

        $this->metrics->gauge('netfly_php_memory_usage_bytes', [], memory_get_usage(true));
        $this->metrics->gauge('netfly_php_memory_peak_bytes', [], memory_get_peak_usage(true));

        $coroutineNum = 0;
        if (class_exists('Swoole\\Coroutine')) {
            $stats = \Swoole\Coroutine::stats();
            $coroutineNum = (int) ($stats['coroutine_num'] ?? 0);
        }

        $this->metrics->gauge('netfly_swoole_coroutine_num', [], $coroutineNum);
    }
}
