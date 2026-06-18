<?php

declare(strict_types=1);

namespace Netfly\Observability\Trace;

final class SpanIdGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function isValid(?string $spanId): bool
    {
        return $spanId !== null && preg_match('/^[A-Za-z0-9._-]{4,64}$/', $spanId) === 1;
    }
}
