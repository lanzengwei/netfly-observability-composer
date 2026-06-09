<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

use Netfly\Observability\Logging\LogType;

final class RedisCollector extends AbstractDependencyCollector
{
    protected function collectorName(): string
    {
        return 'redis';
    }

    protected function dependencyLogType(): string
    {
        return LogType::REDIS;
    }

    protected function durationMetricName(): string
    {
        return 'netfly_redis_command_duration_seconds';
    }

    protected function totalMetricName(DependencyResult $result): string
    {
        return 'netfly_redis_commands_total';
    }

    protected function metricLabels(DependencyResult $result): array
    {
        return array_merge($result->labels, [
            'command' => strtoupper($result->operation),
            'result' => $result->result,
        ]);
    }
}
