<?php

declare(strict_types=1);

namespace Netfly\Observability;

final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'annotations' => [
                'scan' => [
                    'paths' => [__DIR__],
                ],
            ],
            'publish' => [
                [
                    'id' => 'observability',
                    'description' => 'Netfly observability config.',
                    'source' => __DIR__ . '/../config/observability.php',
                    'destination' => BASE_PATH . '/config/autoload/observability.php',
                ],
            ],
        ];
    }
}
