<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class TrafficGenerator
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
        private readonly InventoryService $inventory,
        private readonly MessageService $messages,
        private readonly ObservabilityRuntime $observability
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function mixed(): array
    {
        $created = $this->orders->createOrder();
        $queried = $this->orders->findOrder((int) $created['id']);
        $payment = $this->payments->callback();
        $inventory = $this->inventory->reserve();
        $this->messages->consume('order.created', 'message_consume');

        return [
            'created' => $created,
            'queried' => $queried,
            'payment' => $payment,
            'inventory' => $inventory,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function slow(): array
    {
        $scenario = 'slow_demo';
        usleep(180000);
        $this->observability->mysql('select', [
            'connection' => 'default',
            'database' => 'demo',
            'operation' => 'select',
            'result' => 'success',
        ], 0.19, 'success', null, [
            'scenario' => $scenario,
            'route' => '/demo/slow',
            'table' => 'orders',
        ]);
        $this->observability->redis('GET', [
            'pool' => 'default',
        ], 0.12, 'success', [
            'scenario' => $scenario,
            'route' => '/demo/slow',
            'cache_key_type' => 'order_detail',
        ]);

        return ['slow' => true];
    }

    public function error(): never
    {
        $throwable = new RuntimeException('Controlled demo error');
        $this->observability->error($throwable, [
            'scenario' => 'error_demo',
            'route' => '/demo/error',
            'component' => 'app',
            'operation' => 'controlled_error',
        ]);

        throw $throwable;
    }
}
