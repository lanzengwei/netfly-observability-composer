<?php

declare(strict_types=1);

namespace Netfly\Observability\Logging;

use Netfly\Observability\Config\ObservabilityConfig;
use Netfly\Observability\Context\TraceContext;

final class ObservabilityLogger
{
    private ?RemoteLogSender $remoteLogSender;

    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly JsonLogFormatter $formatter,
        private readonly ?TraceContext $traceContext = null,
        ?RemoteLogSender $remoteLogSender = null
    ) {
        $this->remoteLogSender = $remoteLogSender ?? ($config->remoteLoggingEnabled() ? new RemoteLogSender($config) : null);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $logType, string $message, ?string $traceId, array $context = []): void
    {
        if (! $this->config->loggingEnabled()) {
            return;
        }

        $traceId ??= $this->traceContext?->getTraceId();
        $span = $this->traceContext?->currentSpan();
        if ($span !== null) {
            foreach ($span->toLogContext() as $key => $value) {
                if (! array_key_exists($key, $context)) {
                    $context[$key] = $value;
                }
            }
        }

        $path = $this->config->logPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $line = $this->formatter->format($level, $logType, $message, $traceId, $context);

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        $this->remoteLogSender?->send($line);
    }
}
