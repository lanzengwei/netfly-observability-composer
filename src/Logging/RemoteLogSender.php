<?php

declare(strict_types=1);

namespace Netfly\Observability\Logging;

use Netfly\Observability\Config\ObservabilityConfig;
use Throwable;

final class RemoteLogSender
{
    public function __construct(private readonly ObservabilityConfig $config)
    {
    }

    public function send(string $line): void
    {
        if (! $this->config->remoteLoggingEnabled()) {
            return;
        }

        try {
            if ($this->config->remoteLoggingDriver() === 'http') {
                $this->sendHttp($line);
                return;
            }

            $this->sendTcp($line);
        } catch (Throwable) {
            // Remote logging is best-effort and must not affect application requests.
        }
    }

    private function sendTcp(string $line): void
    {
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->config->remoteLoggingHost(), $this->config->remoteLoggingPort()),
            $errno,
            $errstr,
            $this->timeoutSeconds()
        );

        if (! is_resource($socket)) {
            return;
        }

        [$seconds, $microseconds] = $this->timeoutParts();
        stream_set_timeout($socket, $seconds, $microseconds);
        @fwrite($socket, $line);
        @fclose($socket);
    }

    private function sendHttp(string $line): void
    {
        $parts = parse_url($this->config->remoteLoggingUrl());
        if (! is_array($parts) || ! isset($parts['host'])) {
            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $socket = @stream_socket_client(
            sprintf('%s://%s:%d', $scheme === 'https' ? 'ssl' : 'tcp', $host, $port),
            $errno,
            $errstr,
            $this->timeoutSeconds()
        );

        if (! is_resource($socket)) {
            return;
        }

        $body = rtrim($line, "\r\n");
        $headers = [
            sprintf('POST %s HTTP/1.1', $path),
            sprintf('Host: %s:%d', $host, $port),
            'Content-Type: application/json',
            sprintf('Content-Length: %d', strlen($body)),
            'Connection: close',
        ];

        foreach ($this->config->remoteLoggingHeaders() as $name => $value) {
            $headers[] = sprintf('%s: %s', $name, $value);
        }

        [$seconds, $microseconds] = $this->timeoutParts();
        stream_set_timeout($socket, $seconds, $microseconds);
        @fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body);
        @fclose($socket);
    }

    private function timeoutSeconds(): float
    {
        return $this->config->remoteLoggingTimeoutMs() / 1000;
    }

    /**
     * @return array{int, int}
     */
    private function timeoutParts(): array
    {
        $milliseconds = $this->config->remoteLoggingTimeoutMs();

        return [
            intdiv($milliseconds, 1000),
            ($milliseconds % 1000) * 1000,
        ];
    }
}
