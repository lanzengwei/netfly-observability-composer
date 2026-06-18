<?php

declare(strict_types=1);

namespace Netfly\Observability\Middleware;

use Netfly\Observability\Collector\HttpCollector;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Trace\SpanIdGenerator;
use Netfly\Observability\Trace\TraceIdGenerator;
use Netfly\Observability\Trace\TracePropagator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class TraceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly TraceIdGenerator $traceIdGenerator,
        private readonly TraceContext $traceContext,
        private readonly HttpCollector $collector,
        private readonly ?SpanIdGenerator $spanIdGenerator = null,
        private readonly ?TracePropagator $tracePropagator = null
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $spanIdGenerator = $this->spanIdGenerator ?? new SpanIdGenerator();
        $tracePropagator = $this->tracePropagator ?? new TracePropagator($this->config, $this->traceIdGenerator, $spanIdGenerator);
        $incoming = $tracePropagator->extract($request);
        $traceId = $incoming->traceId;
        $this->traceContext->setTraceId($traceId);
        $rootSpan = $this->traceContext->startSpan(
            $spanIdGenerator->generate(),
            $incoming->parentSpanId,
            sprintf('%s %s', strtoupper($request->getMethod()), $this->routeName($request)),
            'server'
        );
        $start = microtime(true);
        $status = 500;

        try {
            $response = $handler->handle($request);
            $status = $response->getStatusCode();

            $response = $response->withHeader('X-Trace-Id', $traceId);
            foreach ($tracePropagator->headers($this->traceContext) as $header => $value) {
                if ($value !== '') {
                    $response = $response->withHeader($header, $value);
                }
            }

            return $response;
        } catch (Throwable $throwable) {
            throw $throwable;
        } finally {
            if ($this->config->enabled()) {
                $this->collector->record(
                    $traceId,
                    strtoupper($request->getMethod()),
                    $this->routeName($request),
                    $status,
                    microtime(true) - $start,
                    [
                        'uri' => (string) $request->getUri(),
                        'client_ip' => $request->getServerParams()['remote_addr'] ?? null,
                        'user_agent' => $request->getHeaderLine('User-Agent') ?: null,
                    ] + $rootSpan->toLogContext()
                );
            }

            $this->traceContext->clear();
        }
    }

    private function routeName(ServerRequestInterface $request): string
    {
        $route = $request->getAttribute('route');

        if (is_string($route) && $route !== '') {
            return $route;
        }

        return $request->getUri()->getPath() ?: '/';
    }
}
