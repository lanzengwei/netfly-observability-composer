<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Config\ObservabilityConfig;
use PHPUnit\Framework\TestCase;

final class ObservabilityConfigTest extends TestCase
{
    public function test_identity_values_are_normalized(): void
    {
        $config = new ObservabilityConfig([
            'project' => 'demo-shop',
            'env' => 'testing',
            'service' => 'api',
            'metrics' => ['path' => '/internal/metrics'],
            'logging' => ['path' => '/tmp/netfly.log'],
        ]);

        self::assertSame('demo-shop', $config->project());
        self::assertSame('testing', $config->env());
        self::assertSame('api', $config->service());
        self::assertSame('/internal/metrics', $config->metricsPath());
        self::assertSame('/tmp/netfly.log', $config->logPath());
    }

    public function test_master_switch_disables_everything(): void
    {
        $config = new ObservabilityConfig(['enabled' => false]);

        self::assertFalse($config->enabled());
        self::assertFalse($config->metricsEnabled());
        self::assertFalse($config->loggingEnabled());
        self::assertFalse($config->collectorEnabled('http'));
        self::assertFalse($config->collectorEnabled('mysql'));
        self::assertFalse($config->collectorEnabled('redis'));
        self::assertFalse($config->collectorEnabled('rabbitmq'));
    }

    public function test_individual_switches_and_thresholds_are_read(): void
    {
        $config = new ObservabilityConfig([
            'enabled' => true,
            'metrics' => ['enabled' => false],
            'logging' => ['enabled' => true],
            'http' => ['enabled' => false],
            'mysql' => ['enabled' => true],
            'slow_threshold_ms' => [
                'http' => 1500,
                'mysql' => 250,
            ],
        ]);

        self::assertTrue($config->enabled());
        self::assertFalse($config->metricsEnabled());
        self::assertTrue($config->loggingEnabled());
        self::assertFalse($config->collectorEnabled('http'));
        self::assertTrue($config->collectorEnabled('mysql'));
        self::assertSame(1500, $config->slowThresholdMs('http'));
        self::assertSame(250, $config->slowThresholdMs('mysql'));
        self::assertSame(0, $config->slowThresholdMs('missing'));
    }

    public function test_remote_logging_configuration_is_read(): void
    {
        $config = new ObservabilityConfig([
            'logging' => [
                'remote' => [
                    'enabled' => true,
                    'driver' => 'http',
                    'host' => '10.0.0.8',
                    'port' => 19000,
                    'url' => 'http://10.0.0.8:19000/logs',
                    'timeout_ms' => 75,
                    'headers' => ['X-Token' => 'abc'],
                ],
            ],
        ]);

        self::assertTrue($config->remoteLoggingEnabled());
        self::assertSame('http', $config->remoteLoggingDriver());
        self::assertSame('10.0.0.8', $config->remoteLoggingHost());
        self::assertSame(19000, $config->remoteLoggingPort());
        self::assertSame('http://10.0.0.8:19000/logs', $config->remoteLoggingUrl());
        self::assertSame(75, $config->remoteLoggingTimeoutMs());
        self::assertSame(['X-Token' => 'abc'], $config->remoteLoggingHeaders());
    }
}
