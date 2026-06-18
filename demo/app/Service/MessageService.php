<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Throwable;

final class MessageService
{
    public function __construct(private readonly ObservabilityRuntime $observability)
    {
    }

    public function publish(string $event, string $scenario, string $route): void
    {
        $start = microtime(true);
        $result = 'success';
        $errorClass = null;
        $context = [
            'scenario' => $scenario,
            'route' => $route,
            'message_type' => $event,
        ];

        try {
            $this->declareTopicQueue($event);
            $this->request('POST', '/api/exchanges/%2F/orders/publish', [
                'properties' => [
                    'content_type' => 'application/json',
                    'delivery_mode' => 1,
                ],
                'routing_key' => $event,
                'payload' => json_encode([
                    'event' => $event,
                    'scenario' => $scenario,
                    'trace_id' => $this->observability->traceId(),
                    'sent_at' => date(DATE_ATOM),
                ], JSON_THROW_ON_ERROR),
                'payload_encoding' => 'string',
            ]);
        } catch (Throwable $throwable) {
            $result = 'error';
            $errorClass = $throwable::class;
            $context['error'] = $throwable->getMessage();
        }

        $this->observability->rabbitmq('publish', [
            'exchange' => 'orders',
            'queue' => $event,
            'routing_key' => $event,
        ], microtime(true) - $start, $result, $context, $errorClass);
    }

    public function consume(string $event, string $scenario): void
    {
        $start = microtime(true);
        $result = 'success';
        $errorClass = null;
        $context = [
            'scenario' => $scenario,
            'route' => '/demo/traffic',
            'message_type' => $event,
        ];

        try {
            $this->declareTopicQueue($event);
            $this->request('POST', sprintf('/api/queues/%%2F/%s/get', rawurlencode($event)), [
                'count' => 1,
                'ackmode' => 'ack_requeue_false',
                'encoding' => 'auto',
                'truncate' => 50000,
            ]);
        } catch (Throwable $throwable) {
            $result = 'error';
            $errorClass = $throwable::class;
            $context['error'] = $throwable->getMessage();
        }

        $this->observability->rabbitmq('consume', [
            'exchange' => 'orders',
            'queue' => $event,
            'routing_key' => $event,
        ], microtime(true) - $start, $result, $context, $errorClass);
    }

    private function declareTopicQueue(string $queue): void
    {
        $this->request('PUT', '/api/exchanges/%2F/orders', [
            'type' => 'topic',
            'durable' => false,
            'auto_delete' => false,
            'arguments' => new \stdClass(),
        ]);
        $this->request('PUT', sprintf('/api/queues/%%2F/%s', rawurlencode($queue)), [
            'durable' => false,
            'auto_delete' => false,
            'arguments' => new \stdClass(),
        ]);
        $this->request('POST', sprintf('/api/bindings/%%2F/e/orders/q/%s', rawurlencode($queue)), [
            'routing_key' => $queue,
            'arguments' => new \stdClass(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|list<mixed>
     */
    private function request(string $method, string $path, array $payload): array
    {
        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int) (getenv('RABBITMQ_MANAGEMENT_PORT') ?: 15672);
        $user = getenv('RABBITMQ_USER') ?: 'demo';
        $password = getenv('RABBITMQ_PASSWORD') ?: 'demo';
        $url = sprintf('http://%s:%d%s', $host, $port, $path);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode($user . ':' . $password),
                ],
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $status = $this->statusCode($http_response_header ?? []);
        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('RabbitMQ HTTP %s %s failed with status %d', $method, $path, $status));
        }

        if ($response === '') {
            return [];
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<string> $headers
     */
    private function statusCode(array $headers): int
    {
        $first = $headers[0] ?? '';
        if (preg_match('#\s(\d{3})\s#', $first, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
