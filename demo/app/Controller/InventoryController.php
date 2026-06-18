<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InventoryService;

final class InventoryController
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function reserve(): array
    {
        return [
            'ok' => true,
            'scenario' => 'inventory_reserve',
            'inventory' => $this->inventory->reserve(),
        ];
    }
}
