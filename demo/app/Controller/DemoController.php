<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ObservabilityRuntime;
use App\Service\TrafficGenerator;

final class DemoController
{
    public function __construct(
        private readonly TrafficGenerator $traffic,
        private readonly ObservabilityRuntime $observability
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function traffic(): array
    {
        return [
            'ok' => true,
            'scenario' => 'mixed_traffic',
            'result' => $this->traffic->mixed(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function slow(): array
    {
        return [
            'ok' => true,
            'scenario' => 'slow_demo',
            'result' => $this->traffic->slow(),
        ];
    }

    public function error(): never
    {
        $this->traffic->error();
    }

    public function metrics(): string
    {
        return $this->observability->metrics();
    }
}
