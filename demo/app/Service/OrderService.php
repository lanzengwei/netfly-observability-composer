<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly ObservabilityRuntime $observability,
        private readonly OrderRepository $orders,
        private readonly RedisDemoClient $redis,
        private readonly MessageService $messages
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(): array
    {
        $scenario = 'order_create';
        $this->observability->app('info', 'Creating order', [
            'scenario' => $scenario,
            'route' => '/orders',
            'component' => 'app',
            'operation' => 'create_order',
        ]);

        $order = $this->orders->create([
            'sku' => 'SKU-' . random_int(100, 999),
            'quantity' => random_int(1, 4),
        ], $scenario);

        $this->recordRedis('SET', ['order:' . $order['id'], json_encode($order, JSON_THROW_ON_ERROR)], $scenario, '/orders', [
            'scenario' => $scenario,
            'route' => '/orders',
            'cache_key_type' => 'order_detail',
        ]);
        $this->messages->publish('order.created', $scenario, '/orders');

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrder(int $id): array
    {
        $scenario = 'order_query';
        $cached = $this->recordRedis('GET', ['order:' . $id], $scenario, '/orders/{id}', [
            'scenario' => $scenario,
            'route' => '/orders/{id}',
            'cache_key_type' => 'order_detail',
        ]);

        if (is_string($cached) && $cached !== '') {
            $order = json_decode($cached, true);
            if (is_array($order)) {
                $order['status'] = ($order['status'] ?? 'created') . '_cached';

                return $order;
            }
        }

        return $this->orders->find($id, $scenario);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOrders(): array
    {
        return $this->orders->recent('order_query');
    }

    /**
     * @param list<string|int|float> $arguments
     * @param array<string, mixed> $context
     */
    private function recordRedis(string $command, array $arguments, string $scenario, string $route, array $context): mixed
    {
        $start = microtime(true);
        $result = 'success';
        $errorClass = null;
        $response = null;

        try {
            $response = $this->redis->command($command, $arguments);
        } catch (Throwable $throwable) {
            $result = 'error';
            $errorClass = $throwable::class;
            $context['error'] = $throwable->getMessage();
        }

        $this->observability->redis($command, [
            'pool' => 'default',
        ], microtime(true) - $start, $result, $context + [
            'scenario' => $scenario,
            'route' => $route,
        ], $errorClass);

        return $response;
    }
}
