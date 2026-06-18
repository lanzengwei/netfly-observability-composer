<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class RedisDemoClient
{
    /**
     * @param list<string|int|float> $arguments
     */
    public function command(string $command, array $arguments = []): mixed
    {
        $socket = $this->connect();
        $parts = array_merge([strtoupper($command)], array_map(static fn (string|int|float $value): string => (string) $value, $arguments));
        fwrite($socket, $this->encode($parts));
        $response = $this->read($socket);
        fclose($socket);

        return $response;
    }

    /**
     * @return resource
     */
    private function connect()
    {
        $host = getenv('REDIS_HOST') ?: 'redis';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if (! is_resource($socket)) {
            throw new RuntimeException(sprintf('Redis connection failed: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, 1);

        return $socket;
    }

    /**
     * @param list<string> $parts
     */
    private function encode(array $parts): string
    {
        $payload = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $payload .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        }

        return $payload;
    }

    /**
     * @param resource $socket
     */
    private function read($socket): mixed
    {
        $line = fgets($socket);
        if ($line === false || $line === '') {
            throw new RuntimeException('Redis returned an empty response.');
        }

        $prefix = $line[0];
        $value = rtrim(substr($line, 1), "\r\n");

        return match ($prefix) {
            '+' => $value,
            ':' => (int) $value,
            '$' => $this->readBulkString($socket, (int) $value),
            '*' => $this->readArray($socket, (int) $value),
            '-' => throw new RuntimeException('Redis error: ' . $value),
            default => throw new RuntimeException('Unknown Redis response: ' . $line),
        };
    }

    /**
     * @param resource $socket
     */
    private function readBulkString($socket, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }

        $value = '';
        while (strlen($value) < $length) {
            $chunk = fread($socket, $length - strlen($value));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Redis bulk string response ended early.');
            }
            $value .= $chunk;
        }
        fread($socket, 2);

        return $value;
    }

    /**
     * @param resource $socket
     * @return list<mixed>
     */
    private function readArray($socket, int $count): array
    {
        $values = [];
        for ($index = 0; $index < $count; ++$index) {
            $values[] = $this->read($socket);
        }

        return $values;
    }
}
