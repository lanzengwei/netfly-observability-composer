<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PaymentService;

final class PaymentController
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function callback(): array
    {
        return [
            'ok' => true,
            'scenario' => 'payment_callback',
            'payment' => $this->payments->callback(),
        ];
    }
}
