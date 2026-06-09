<?php

declare(strict_types=1);

namespace Netfly\Observability\Context;

use Hyperf\Context\Context;

final class TraceContext
{
    private const KEY = 'netfly.trace_id';

    private ?string $fallbackTraceId = null;

    public function setTraceId(string $traceId): void
    {
        if ($this->hyperfContextAvailable()) {
            Context::set(self::KEY, $traceId);
            return;
        }

        $this->fallbackTraceId = $traceId;
    }

    public function getTraceId(): ?string
    {
        if ($this->hyperfContextAvailable() && Context::has(self::KEY)) {
            $value = Context::get(self::KEY);

            return is_string($value) ? $value : null;
        }

        return $this->fallbackTraceId;
    }

    public function clear(): void
    {
        if ($this->hyperfContextAvailable()) {
            Context::destroy(self::KEY);
        }

        $this->fallbackTraceId = null;
    }

    private function hyperfContextAvailable(): bool
    {
        return class_exists(Context::class) && class_exists('Swoole\\Coroutine');
    }
}
