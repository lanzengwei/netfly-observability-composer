<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;

final class OrderController
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(): array
    {
        return [
            'ok' => true,
            'scenario' => 'order_create',
            'order' => $this->orders->createOrder(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return [
            'ok' => true,
            'scenario' => 'order_query',
            'orders' => $this->orders->listOrders(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        return [
            'ok' => true,
            'scenario' => 'order_query',
            'order' => $this->orders->findOrder($id),
        ];
    }
}
