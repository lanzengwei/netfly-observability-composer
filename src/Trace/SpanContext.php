<?php

declare(strict_types=1);

namespace Netfly\Observability\Trace;

final class SpanContext
{
    public function __construct(
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
        public readonly ?string $spanName = null,
        public readonly ?string $spanKind = null
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function toLogContext(): array
    {
        return [
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'span_name' => $this->spanName,
            'span_kind' => $this->spanKind,
        ];
    }
}
