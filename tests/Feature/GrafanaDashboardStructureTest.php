<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class GrafanaDashboardStructureTest extends TestCase
{
    public function test_dashboard_directory_contains_new_scenario_and_component_set(): void
    {
        $dashboardDir = dirname(__DIR__, 2) . '/docker/grafana/dashboards';
        $files = array_map('basename', glob($dashboardDir . '/*.json') ?: []);
        sort($files);

        self::assertSame([
            'netfly-errors-slow.json',
            'netfly-mysql.json',
            'netfly-overview.json',
            'netfly-rabbitmq.json',
            'netfly-raw-logs.json',
            'netfly-redis.json',
            'netfly-request-journey.json',
        ], $files);
    }

    public function test_new_dashboards_are_scenario_and_component_oriented(): void
    {
        $dashboardDir = dirname(__DIR__, 2) . '/docker/grafana/dashboards';
        $journey = (string) file_get_contents($dashboardDir . '/netfly-request-journey.json');
        $mysql = (string) file_get_contents($dashboardDir . '/netfly-mysql.json');
        $redis = (string) file_get_contents($dashboardDir . '/netfly-redis.json');
        $rabbitmq = (string) file_get_contents($dashboardDir . '/netfly-rabbitmq.json');

        self::assertStringContainsString('"title": "Netfly Request Journey"', $journey);
        self::assertStringContainsString('"name": "scenario"', $journey);
        self::assertStringContainsString('span_id', $journey);
        self::assertStringContainsString('parent_span_id', $journey);
        self::assertStringContainsString('"title": "Netfly MySQL"', $mysql);
        self::assertStringContainsString('log_type=\"db\"', $mysql);
        self::assertStringContainsString('"title": "Netfly Redis"', $redis);
        self::assertStringContainsString('log_type=\"redis\"', $redis);
        self::assertStringContainsString('"title": "Netfly RabbitMQ"', $rabbitmq);
        self::assertStringContainsString('log_type=\"amqp\"', $rabbitmq);
    }

    public function test_visual_dashboard_directory_is_loaded_as_separate_folder(): void
    {
        $root = dirname(__DIR__, 2);
        $visualDir = $root . '/docker/grafana/visual-dashboards';
        $files = array_map('basename', glob($visualDir . '/*.json') ?: []);
        sort($files);

        self::assertSame([
            'netfly-visual-dependency-map.json',
            'netfly-visual-request-map.json',
        ], $files);

        $provider = (string) file_get_contents($root . '/docker/grafana/provisioning/dashboards/dashboards.yml');
        $compose = (string) file_get_contents($root . '/docker-compose.yml');
        $requestMap = (string) file_get_contents($visualDir . '/netfly-visual-request-map.json');
        $dependencyMap = (string) file_get_contents($visualDir . '/netfly-visual-dependency-map.json');

        self::assertStringContainsString('Netfly Visual Maps', $provider);
        self::assertStringContainsString('folder: Netfly Visual', $provider);
        self::assertStringContainsString('/var/lib/grafana/visual-dashboards', $provider);
        self::assertStringContainsString('./docker/grafana/visual-dashboards:/var/lib/grafana/visual-dashboards:ro', $compose);
        self::assertStringContainsString('"title": "Netfly Visual Request Map"', $requestMap);
        self::assertStringContainsString('"title": "Netfly Visual Dependency Map"', $dependencyMap);
        self::assertStringContainsString('Trace Timeline', $requestMap);
        self::assertStringContainsString('Dependency Topology', $dependencyMap);
    }

    public function test_promtail_extracts_scenario_and_component_labels(): void
    {
        $promtail = (string) file_get_contents(dirname(__DIR__, 2) . '/docker/promtail/promtail.yml');

        self::assertStringContainsString('scenario: scenario', $promtail);
        self::assertStringContainsString('component: component', $promtail);
        self::assertStringContainsString("          scenario:\n", $promtail);
        self::assertStringContainsString("          component:\n", $promtail);
    }
}
