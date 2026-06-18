<?php

declare(strict_types=1);

namespace Netfly\Observability\Context;

use Hyperf\Context\Context;
use Netfly\Observability\Trace\SpanContext;

final class TraceContext
{
    private const KEY = 'netfly.trace_id';
    private const SPAN_STACK_KEY = 'netfly.span_stack';

    private ?string $fallbackTraceId = null;

    /**
     * @var list<SpanContext>
     */
    private array $fallbackSpanStack = [];

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
            Context::destroy(self::SPAN_STACK_KEY);
        }

        $this->fallbackTraceId = null;
        $this->fallbackSpanStack = [];
    }

    public function startSpan(string $spanId, ?string $parentSpanId = null, ?string $spanName = null, ?string $spanKind = null): SpanContext
    {
        $span = new SpanContext($spanId, $parentSpanId, $spanName, $spanKind);
        $stack = $this->spanStack();
        $stack[] = $span;
        $this->setSpanStack($stack);

        return $span;
    }

    public function createChildSpan(string $spanId, ?string $spanName = null, ?string $spanKind = null): SpanContext
    {
        return new SpanContext($spanId, $this->currentSpan()?->spanId, $spanName, $spanKind);
    }

    public function currentSpan(): ?SpanContext
    {
        $stack = $this->spanStack();

        if ($stack === []) {
            return null;
        }

        return $stack[array_key_last($stack)];
    }

    public function endSpan(?string $spanId = null): void
    {
        $stack = $this->spanStack();

        if ($stack === []) {
            return;
        }

        if ($spanId === null || end($stack)->spanId === $spanId) {
            array_pop($stack);
            $this->setSpanStack($stack);
            return;
        }

        for ($index = count($stack) - 1; $index >= 0; --$index) {
            if ($stack[$index]->spanId === $spanId) {
                array_splice($stack, $index, 1);
                $this->setSpanStack(array_values($stack));
                return;
            }
        }
    }

    private function hyperfContextAvailable(): bool
    {
        return class_exists(Context::class) && class_exists('Swoole\\Coroutine');
    }

    /**
     * @return list<SpanContext>
     */
    private function spanStack(): array
    {
        if ($this->hyperfContextAvailable() && Context::has(self::SPAN_STACK_KEY)) {
            $value = Context::get(self::SPAN_STACK_KEY);

            return is_array($value) ? array_values(array_filter($value, static fn (mixed $span): bool => $span instanceof SpanContext)) : [];
        }

        return $this->fallbackSpanStack;
    }

    /**
     * @param list<SpanContext> $stack
     */
    private function setSpanStack(array $stack): void
    {
        if ($this->hyperfContextAvailable()) {
            Context::set(self::SPAN_STACK_KEY, $stack);
            return;
        }

        $this->fallbackSpanStack = $stack;
    }
}
