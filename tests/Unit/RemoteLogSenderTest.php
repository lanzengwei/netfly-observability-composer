<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Logging\RemoteLogSender;
use PHPUnit\Framework\TestCase;

final class RemoteLogSenderTest extends TestCase
{
    public function test_sends_tcp_json_line_to_configured_host_and_port(): void
    {
        [$server, $host, $port] = $this->listen();
        $sender = new RemoteLogSender(new ObservabilityConfig([
            'logging' => [
                'remote' => [
                    'enabled' => true,
                    'driver' => 'tcp',
                    'host' => $host,
                    'port' => $port,
                    'timeout_ms' => 100,
                ],
            ],
        ]));

        $sender->send("{\"message\":\"hello\"}\n");

        $connection = stream_socket_accept($server, 1);
        self::assertIsResource($connection);
        self::assertSame("{\"message\":\"hello\"}\n", stream_get_contents($connection));

        fclose($connection);
        fclose($server);
    }

    public function test_sends_http_post_to_configured_url(): void
    {
        [$server, $host, $port] = $this->listen();
        $sender = new RemoteLogSender(new ObservabilityConfig([
            'logging' => [
                'remote' => [
                    'enabled' => true,
                    'driver' => 'http',
                    'url' => sprintf('http://%s:%d/logs', $host, $port),
                    'timeout_ms' => 100,
                    'headers' => ['X-Token' => 'abc'],
                ],
            ],
        ]));

        $sender->send("{\"message\":\"hello\"}\n");

        $connection = stream_socket_accept($server, 1);
        self::assertIsResource($connection);
        $request = stream_get_contents($connection);

        self::assertStringContainsString("POST /logs HTTP/1.1\r\n", $request);
        self::assertStringContainsString(sprintf("Host: %s:%d\r\n", $host, $port), $request);
        self::assertStringContainsString("Content-Type: application/json\r\n", $request);
        self::assertStringContainsString("X-Token: abc\r\n", $request);
        self::assertStringEndsWith("\r\n\r\n{\"message\":\"hello\"}", $request);

        fclose($connection);
        fclose($server);
    }

    /**
     * @return array{resource, string, int}
     */
    private function listen(): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($server, $errstr);
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $parts = explode(':', $name);
        $port = (int) $parts[count($parts) - 1];

        return [$server, '127.0.0.1', $port];
    }
}
