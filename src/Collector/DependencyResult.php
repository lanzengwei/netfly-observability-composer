<?php

declare(strict_types=1);

namespace Netfly\Observability\Collector;

final class DependencyResult
{
    /**
     * @param array<string, mixed> $labels
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $component,
        public readonly string $operation,
        public readonly array $labels,
        public readonly float $durationSeconds,
        public readonly string $result = 'success',
        public readonly ?string $errorClass = null,
        public readonly array $context = []
    ) {
    }

    public function durationMs(): float
    {
        return round($this->durationSeconds * 1000, 3);
    }
}
