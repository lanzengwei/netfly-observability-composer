<?php

declare(strict_types=1);

namespace Netfly\Observability\Trace;

final class IncomingTraceContext
{
    public function __construct(
        public readonly string $traceId,
        public readonly ?string $parentSpanId = null
    ) {
    }
}
