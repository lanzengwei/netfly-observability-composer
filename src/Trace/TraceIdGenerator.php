<?php

declare(strict_types=1);

namespace Netfly\Observability\Trace;

final class TraceIdGenerator
{
    public function fromHeader(?string $traceId): string
    {
        if ($traceId !== null && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $traceId) === 1) {
            return $traceId;
        }

        return $this->generate();
    }

    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
