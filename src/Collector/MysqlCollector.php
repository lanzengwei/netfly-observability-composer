<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

use Netfly\Observability\Logging\LogType;

final class MysqlCollector extends AbstractDependencyCollector
{
    protected function collectorName(): string
    {
        return 'mysql';
    }

    protected function dependencyLogType(): string
    {
        return LogType::DB;
    }

    protected function durationMetricName(): string
    {
        return 'netfly_db_query_duration_seconds';
    }

    protected function totalMetricName(DependencyResult $result): string
    {
        return 'netfly_db_queries_total';
    }

    protected function metricLabels(DependencyResult $result): array
    {
        return array_merge($result->labels, [
            'operation' => strtolower($result->operation),
            'result' => $result->result,
        ]);
    }
}
