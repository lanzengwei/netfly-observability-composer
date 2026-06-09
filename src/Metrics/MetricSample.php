<?php

declare(strict_types=1);

namespace Netfly\Observability\Metrics;

final class MetricSample
{
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $labels,
        public readonly float|int $value
    ) {
    }
}
