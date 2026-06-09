<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Logging\JsonLogFormatter;
use Netfly\Observability\Logging\ObservabilityLogger;
use PHPUnit\Framework\TestCase;

final class ObservabilityLoggerTest extends TestCase
{
    public function test_writes_json_line_when_enabled(): void
    {
        $path = sys_get_temp_dir() . '/netfly-observability-test-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'project' => 'shop',
            'env' => 'local',
            'service' => 'api',
            'logging' => ['enabled' => true, 'path' => $path],
        ]);
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));

        $logger->log('info', 'app', 'hello', 'trace-1', ['a' => 'b']);

        $line = file_get_contents($path);
        self::assertIsString($line);
        self::assertStringContainsString('"trace_id":"trace-1"', $line);

        @unlink($path);
    }

    public function test_does_not_write_when_logging_disabled(): void
    {
        $path = sys_get_temp_dir() . '/netfly-observability-test-' . bin2hex(random_bytes(4)) . '.log';
        $config = new ObservabilityConfig([
            'logging' => ['enabled' => false, 'path' => $path],
        ]);
        $logger = new ObservabilityLogger($config, new JsonLogFormatter($config->identityLabels()));

        $logger->log('info', 'app', 'hello', 'trace-1');

        self::assertFileDoesNotExist($path);
    }
}
