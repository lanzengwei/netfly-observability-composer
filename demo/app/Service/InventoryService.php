<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use Throwable;

final class InventoryService
{
    public function __construct(
        private readonly ObservabilityRuntime $observability,
        private readonly OrderRepository $orders,
        private readonly RedisDemoClient $redis
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function reserve(): array
    {
        $scenario = 'inventory_reserve';
        $sku = 'SKU-' . random_int(100, 999);
        $start = microtime(true);
        $result = 'success';
        $errorClass = null;
        $context = [
            'scenario' => $scenario,
            'route' => '/inventory/reserve',
            'cache_key_type' => 'inventory_stock',
            'sku' => $sku,
        ];

        try {
            $this->redis->command('SET', ['inventory:' . $sku, 200]);
            $this->redis->command('DECRBY', ['inventory:' . $sku, random_int(1, 3)]);
        } catch (Throwable $throwable) {
            $result = 'error';
            $errorClass = $throwable::class;
            $context['error'] = $throwable->getMessage();
        }

        $this->observability->redis('DECRBY', [
            'pool' => 'default',
        ], microtime(true) - $start, $result, $context, $errorClass);
        $this->orders->updateStatus(random_int(1000, 9999), 'reserved', $scenario, '/inventory/reserve');

        return [
            'sku' => $sku,
            'reserved' => true,
        ];
    }
}
