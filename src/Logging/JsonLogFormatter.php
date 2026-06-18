<?php

declare(strict_types=1);

namespace Netfly\Observability\Logging;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class JsonLogFormatter
{
    private LogSanitizer $sanitizer;

    /**
     * @param array{project: string, env: string, service: string} $identity
     */
    public function __construct(private readonly array $identity, ?LogSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new LogSanitizer();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function format(string $level, string $logType, string $message, ?string $traceId, array $context = []): string
    {
        $record = [
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::RFC3339_EXTENDED),
            'project' => $this->identity['project'],
            'env' => $this->identity['env'],
            'service' => $this->identity['service'],
            'level' => $level,
            'log_type' => $logType,
            'trace_id' => $traceId,
            'span_id' => $context['span_id'] ?? null,
            'parent_span_id' => $context['parent_span_id'] ?? null,
            'message' => $message,
            'context' => $this->sanitizer->sanitize($context),
        ];

        foreach ([
            'duration_ms',
            'threshold_ms',
            'component',
            'operation',
            'route',
            'status',
            'exception_class',
            'span_name',
            'span_kind',
            'result',
            'scenario',
            'method',
            'table',
            'cache_key_type',
            'exchange',
            'queue',
            'routing_key',
        ] as $field) {
            if (array_key_exists($field, $context)) {
                $record[$field] = $context[$field];
            }
        }

        return json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
