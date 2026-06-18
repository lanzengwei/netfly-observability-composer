<?php

declare(strict_types=1);

namespace Netfly\Observability\Trace;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Psr\Http\Message\ServerRequestInterface;

final class TracePropagator
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly TraceIdGenerator $traceIdGenerator,
        private readonly SpanIdGenerator $spanIdGenerator
    ) {
    }

    public function extract(ServerRequestInterface $request): IncomingTraceContext
    {
        $headers = $this->config->traceHeaders();
        $traceHeader = $request->getHeaderLine($headers['trace_id']) ?: $request->getHeaderLine('X-Trace-Id');
        $parentSpanId = $request->getHeaderLine($headers['span_id']) ?: null;
        $remoteParentSpanId = $request->getHeaderLine($headers['parent_span_id']) ?: null;

        return new IncomingTraceContext(
            $this->traceIdGenerator->fromHeader($traceHeader ?: null),
            $this->spanIdGenerator->isValid($parentSpanId) ? $parentSpanId : null,
            $this->spanIdGenerator->isValid($remoteParentSpanId) ? $remoteParentSpanId : null
        );
    }

    /**
     * @return array<string, string>
     */
    public function headers(TraceContext $context): array
    {
        $headers = $this->config->traceHeaders();
        $span = $context->currentSpan();

        return [
            $headers['trace_id'] => $context->getTraceId() ?? '',
            $headers['span_id'] => $span?->spanId ?? '',
            $headers['parent_span_id'] => $span?->parentSpanId ?? '',
        ];
    }
}
