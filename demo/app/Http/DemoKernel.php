<?php

declare(strict_types=1);

namespace App\Http;

use App\Controller\DemoController;
use App\Controller\InventoryController;
use App\Controller\OrderController;
use App\Controller\PaymentController;
use App\Repository\OrderRepository;
use App\Service\InventoryService;
use App\Service\MessageService;
use App\Service\ObservabilityRuntime;
use App\Service\OrderService;
use App\Service\PaymentService;
use App\Service\RedisDemoClient;
use App\Service\TrafficGenerator;
use Throwable;

final class DemoKernel
{
    private readonly ObservabilityRuntime $observability;

    private readonly OrderController $orders;

    private readonly PaymentController $payments;

    private readonly InventoryController $inventory;

    private readonly DemoController $demo;

    public function __construct()
    {
        $this->observability = new ObservabilityRuntime();
        $messages = new MessageService($this->observability);
        $redis = new RedisDemoClient();
        $repository = new OrderRepository($this->observability);
        $orderService = new OrderService($this->observability, $repository, $redis, $messages);
        $paymentService = new PaymentService($this->observability, $repository, $messages);
        $inventoryService = new InventoryService($this->observability, $repository, $redis);
        $traffic = new TrafficGenerator($orderService, $paymentService, $inventoryService, $messages, $this->observability);

        $this->orders = new OrderController($orderService);
        $this->payments = new PaymentController($paymentService);
        $this->inventory = new InventoryController($inventoryService);
        $this->demo = new DemoController($traffic, $this->observability);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function dispatch(string $method, string $path, array $headers = []): array
    {
        if ($path === '/metrics') {
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/plain; version=0.0.4'],
                'body' => $this->demo->metrics(),
            ];
        }

        $route = $this->routeName($method, $path);
        $scenario = $this->scenario($method, $path);
        $start = microtime(true);
        $status = 200;
        $body = [];

        $this->observability->begin($method, $route, $scenario, $headers);

        try {
            $body = $this->handle($method, $path);
            if (($body['ok'] ?? true) === false && ($body['message'] ?? null) === 'not found') {
                $status = 404;
            }
        } catch (Throwable $throwable) {
            $status = 500;
            if ($scenario !== 'error_demo') {
                $this->observability->error($throwable, [
                    'scenario' => $scenario,
                    'route' => $route,
                    'component' => 'app',
                    'operation' => 'unhandled_exception',
                ]);
            }
            $body = [
                'ok' => false,
                'scenario' => $scenario,
                'message' => $throwable->getMessage(),
            ];
        }

        $responseHeaders = [
            'Content-Type' => 'application/json',
            'X-Trace-Id' => $this->observability->traceId(),
            'X-Netfly-Trace-Id' => $this->observability->traceId(),
            'X-Netfly-Span-Id' => $this->observability->spanId() ?? '',
        ];
        $this->observability->finish($method, $route, $status, microtime(true) - $start, [
            'scenario' => $scenario,
            'route' => $route,
            'method' => strtoupper($method),
            'uri' => $path,
        ]);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(string $method, string $path): array
    {
        $method = strtoupper($method);

        if ($method === 'GET' && $path === '/') {
            return [
                'ok' => true,
                'service' => 'demo-api',
                'endpoints' => ['/orders', '/orders/{id}', '/payments/callback', '/inventory/reserve', '/demo/traffic', '/demo/slow', '/demo/error', '/metrics'],
            ];
        }

        if ($method === 'POST' && $path === '/orders') {
            return $this->orders->create();
        }

        if ($method === 'GET' && $path === '/orders') {
            return $this->orders->index();
        }

        if ($method === 'GET' && preg_match('#^/orders/(\d+)$#', $path, $matches) === 1) {
            return $this->orders->show((int) $matches[1]);
        }

        if ($method === 'POST' && $path === '/payments/callback') {
            return $this->payments->callback();
        }

        if ($method === 'POST' && $path === '/inventory/reserve') {
            return $this->inventory->reserve();
        }

        if ($method === 'GET' && $path === '/demo/traffic') {
            return $this->demo->traffic();
        }

        if ($method === 'GET' && $path === '/demo/slow') {
            return $this->demo->slow();
        }

        if ($method === 'GET' && $path === '/demo/error') {
            return $this->demo->error();
        }

        return [
            'ok' => false,
            'message' => 'not found',
        ];
    }

    private function routeName(string $method, string $path): string
    {
        if (preg_match('#^/orders/\d+$#', $path) === 1) {
            return '/orders/{id}';
        }

        return $path === '/' ? '/' : $path;
    }

    private function scenario(string $method, string $path): string
    {
        $method = strtoupper($method);

        return match (true) {
            $method === 'POST' && $path === '/orders' => 'order_create',
            $method === 'GET' && ($path === '/orders' || preg_match('#^/orders/\d+$#', $path) === 1) => 'order_query',
            $method === 'POST' && $path === '/payments/callback' => 'payment_callback',
            $method === 'POST' && $path === '/inventory/reserve' => 'inventory_reserve',
            $method === 'GET' && $path === '/demo/traffic' => 'mixed_traffic',
            $method === 'GET' && $path === '/demo/slow' => 'slow_demo',
            $method === 'GET' && $path === '/demo/error' => 'error_demo',
            default => 'demo_home',
        };
    }
}
