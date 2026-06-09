<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Logging\LogType;
use Netfly\Observability\Logging\ObservabilityLogger;
use Netfly\Observability\Metrics\MetricsRegistry;

abstract class AbstractDependencyCollector
{
    public function __construct(
        protected readonly ObservabilityConfig $config,
        protected readonly MetricsRegistry $metrics,
        protected readonly ObservabilityLogger $logger,
        protected readonly TraceContext $traceContext
    ) {
    }

    public function record(DependencyResult $result): void
    {
        if (! $this->config->collectorEnabled($this->collectorName())) {
            return;
        }

        $labels = $this->metricLabels($result);

        if ($this->config->metricsEnabled()) {
            $this->metrics->counter($this->totalMetricName($result), $labels);
            $this->metrics->histogram($this->durationMetricName(), $labels, $result->durationSeconds);
        }

        $context = array_merge($result->context, $labels, [
            'component' => $this->collectorName(),
            'operation' => $result->operation,
            'duration_ms' => $result->durationMs(),
            'result' => $result->result,
            'exception_class' => $result->errorClass,
        ]);

        $this->logger->log(
            $result->result === 'error' ? 'error' : 'info',
            $this->dependencyLogType(),
            $this->message($result),
            $this->traceContext->getTraceId(),
            $context
        );

        $threshold = $this->config->slowThresholdMs($this->collectorName());
        if ($threshold > 0 && $result->durationMs() >= $threshold) {
            $this->logger->log('warning', LogType::SLOW, 'Slow dependency call', $this->traceContext->getTraceId(), array_merge($context, [
                'threshold_ms' => $threshold,
            ]));
        }
    }

    abstract protected function collectorName(): string;

    abstract protected function dependencyLogType(): string;

    abstract protected function durationMetricName(): string;

    abstract protected function totalMetricName(DependencyResult $result): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function metricLabels(DependencyResult $result): array;

    protected function message(DependencyResult $result): string
    {
        return sprintf('%s %s %s', $this->collectorName(), $result->operation, $result->result);
    }
}
