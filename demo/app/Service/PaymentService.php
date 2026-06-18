<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;

final class PaymentService
{
    public function __construct(
        private readonly ObservabilityRuntime $observability,
        private readonly OrderRepository $orders,
        private readonly MessageService $messages
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function callback(): array
    {
        $scenario = 'payment_callback';
        $orderId = random_int(1000, 9999);
        $this->observability->app('info', 'Payment callback received', [
            'scenario' => $scenario,
            'route' => '/payments/callback',
            'component' => 'app',
            'operation' => 'payment_callback',
            'order_id' => $orderId,
        ]);
        $this->orders->updateStatus($orderId, 'paid', $scenario, '/payments/callback');
        $this->messages->publish('payment.succeeded', $scenario, '/payments/callback');

        return [
            'order_id' => $orderId,
            'status' => 'paid',
        ];
    }
}
