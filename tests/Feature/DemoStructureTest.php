<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DemoStructureTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_demo_uses_hyperf_structure_instead_of_single_file_entry(): void
    {
        self::assertFileDoesNotExist($this->root . '/demo/public/index.php');
        self::assertFileDoesNotExist($this->root . '/demo/app/Service/TrafficService.php');

        foreach ([
            '/demo/bin/hyperf.php',
            '/demo/config/autoload/routes.php',
            '/demo/config/autoload/server.php',
            '/demo/config/autoload/dependencies.php',
            '/demo/config/autoload/middlewares.php',
            '/demo/config/autoload/observability.php',
            '/demo/app/Controller/OrderController.php',
            '/demo/app/Controller/PaymentController.php',
            '/demo/app/Controller/InventoryController.php',
            '/demo/app/Controller/DemoController.php',
            '/demo/app/Repository/OrderRepository.php',
            '/demo/app/Service/OrderService.php',
            '/demo/app/Service/PaymentService.php',
            '/demo/app/Service/InventoryService.php',
            '/demo/app/Service/MessageService.php',
            '/demo/app/Service/RedisDemoClient.php',
            '/demo/app/Service/TrafficGenerator.php',
        ] as $path) {
            self::assertFileExists($this->root . $path, $path . ' should exist');
        }
    }

    public function test_demo_dockerfile_starts_hyperf_server(): void
    {
        $dockerfile = (string) file_get_contents($this->root . '/demo/Dockerfile');
        $entrypoint = (string) file_get_contents($this->root . '/demo/docker-entrypoint.sh');

        self::assertStringContainsString('docker-entrypoint.sh', $dockerfile);
        self::assertStringContainsString('printf "no\nno\nno\nno\nno\nno\nno\nno\nno\nno\n" | pecl install swoole-5.1.7', $dockerfile);
        self::assertStringContainsString('php /app/demo/bin/hyperf.php start', $entrypoint);
        self::assertStringNotContainsString('php -S 0.0.0.0:9501', $dockerfile);
        self::assertStringNotContainsString('php -S 0.0.0.0:9501', $entrypoint);
    }

    public function test_demo_uses_real_dependency_clients_for_scenario_data(): void
    {
        $repository = (string) file_get_contents($this->root . '/demo/app/Repository/OrderRepository.php');
        $redis = (string) file_get_contents($this->root . '/demo/app/Service/RedisDemoClient.php');
        $messages = (string) file_get_contents($this->root . '/demo/app/Service/MessageService.php');

        self::assertStringContainsString('new PDO(', $repository);
        self::assertStringContainsString('INSERT INTO orders', $repository);
        self::assertStringContainsString('information_schema.COLUMNS', $repository);
        self::assertStringContainsString('fsockopen', $redis);
        self::assertStringContainsString('/api/exchanges/%2F/orders/publish', $messages);
        self::assertStringContainsString('/api/queues/%%2F/%s/get', $messages);
    }
}
