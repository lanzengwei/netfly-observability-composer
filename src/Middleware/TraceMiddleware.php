<?php

declare(strict_types=1);

namespace Netfly\Observability\Middleware;

use Netfly\Observability\Collector\HttpCollector;
use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;
use Netfly\Observability\Trace\TraceIdGenerator;
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
        private readonly HttpCollector $collector
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $traceId = $this->traceIdGenerator->fromHeader($request->getHeaderLine('X-Trace-Id') ?: null);
        $this->traceContext->setTraceId($traceId);
        $start = microtime(true);
        $status = 500;

        try {
            $response = $handler->handle($request);
            $status = $response->getStatusCode();

            return $response->withHeader('X-Trace-Id', $traceId);
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
                    ]
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
