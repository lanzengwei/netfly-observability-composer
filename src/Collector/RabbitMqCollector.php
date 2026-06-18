<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

use Netfly\Observability\Logging\LogType;

final class RabbitMqCollector extends AbstractDependencyCollector
{
    protected function collectorName(): string
    {
        return 'rabbitmq';
    }

    protected function dependencyLogType(): string
    {
        return LogType::AMQP;
    }

    protected function durationMetricName(): string
    {
        return 'netfly_amqp_duration_seconds';
    }

    protected function totalMetricName(DependencyResult $result): string
    {
        return strtolower($result->operation) === 'consume'
            ? 'netfly_amqp_consume_total'
            : 'netfly_amqp_publish_total';
    }

    protected function metricLabels(DependencyResult $result): array
    {
        return array_merge($result->labels, [
            'operation' => strtolower($result->operation),
            'result' => $result->result,
        ]);
    }

    protected function spanKind(DependencyResult $result): string
    {
        return strtolower($result->operation) === 'consume' ? 'consumer' : 'producer';
    }
}
