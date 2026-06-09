<?php

declare(strict_types=1);

namespace Netfly\Observability\Logging;

use Netfly\Observability\Config\ObservabilityConfig;

final class ObservabilityLogger
{
    public function __construct(
        private readonly ObservabilityConfig $config,
        private readonly JsonLogFormatter $formatter
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $logType, string $message, ?string $traceId, array $context = []): void
    {
        if (! $this->config->loggingEnabled()) {
            return;
        }

        $path = $this->config->logPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $path,
            $this->formatter->format($level, $logType, $message, $traceId, $context),
            FILE_APPEND | LOCK_EX
        );
    }
}
