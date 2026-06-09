<?php

declare(strict_types=1);

namespace Netfly\Observability\Controller;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Metrics\MetricsRegistry;
use Netfly\Observability\Metrics\PrometheusRenderer;

final class MetricsController
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly MetricsRegistry $metrics,
        private readonly PrometheusRenderer $renderer
    ) {
    }

    public function text(): string
    {
        if (! $this->config->metricsEnabled()) {
            return '';
        }

        return $this->renderer->render($this->metrics->samples());
    }
}
