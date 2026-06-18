<?php

declare(strict_types=1);

namespace Netfly\Observability\Config;

final class ObservabilityConfig
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive($this->defaults(), $config);
    }

    public function enabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function project(): string
    {
        return $this->stringValue('project', 'default');
    }

    public function env(): string
    {
        return $this->stringValue('env', 'local');
    }

    public function service(): string
    {
        return $this->stringValue('service', 'hyperf');
    }

    public function metricsPath(): string
    {
        return (string) ($this->config['metrics']['path'] ?? '/metrics');
    }

    public function logPath(): string
    {
        return (string) ($this->config['logging']['path'] ?? $this->basePath() . '/runtime/logs/netfly-observability.log');
    }

    public function metricsEnabled(): bool
    {
        return $this->enabled() && (bool) ($this->config['metrics']['enabled'] ?? true);
    }

    public function loggingEnabled(): bool
    {
        return $this->enabled() && (bool) ($this->config['logging']['enabled'] ?? true);
    }

    public function remoteLoggingEnabled(): bool
    {
        return $this->loggingEnabled() && (bool) ($this->config['logging']['remote']['enabled'] ?? false);
    }

    public function remoteLoggingDriver(): string
    {
        $driver = strtolower($this->stringValueFromPath(['logging', 'remote', 'driver'], 'tcp'));

        return in_array($driver, ['tcp', 'http'], true) ? $driver : 'tcp';
    }

    public function remoteLoggingHost(): string
    {
        return $this->stringValueFromPath(['logging', 'remote', 'host'], '127.0.0.1');
    }

    public function remoteLoggingPort(): int
    {
        return max(1, (int) ($this->config['logging']['remote']['port'] ?? 9000));
    }

    public function remoteLoggingUrl(): string
    {
        return $this->stringValueFromPath(['logging', 'remote', 'url'], 'http://127.0.0.1:9000/logs');
    }

    public function remoteLoggingTimeoutMs(): int
    {
        return max(1, (int) ($this->config['logging']['remote']['timeout_ms'] ?? 50));
    }

    /**
     * @return array<string, string>
     */
    public function remoteLoggingHeaders(): array
    {
        $headers = $this->config['logging']['remote']['headers'] ?? [];
        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $normalized[$name] = (string) $value;
            }
        }

        return $normalized;
    }

    public function collectorEnabled(string $name): bool
    {
        return $this->enabled() && (bool) ($this->config[$name]['enabled'] ?? true);
    }

    /**
     * @return array{trace_id: string, span_id: string, parent_span_id: string}
     */
    public function traceHeaders(): array
    {
        $headers = $this->config['trace']['headers'] ?? [];

        return [
            'trace_id' => is_scalar($headers['trace_id'] ?? null) ? (string) $headers['trace_id'] : 'X-Netfly-Trace-Id',
            'span_id' => is_scalar($headers['span_id'] ?? null) ? (string) $headers['span_id'] : 'X-Netfly-Span-Id',
            'parent_span_id' => is_scalar($headers['parent_span_id'] ?? null) ? (string) $headers['parent_span_id'] : 'X-Netfly-Parent-Span-Id',
        ];
    }

    public function slowThresholdMs(string $name): int
    {
        return (int) ($this->config['slow_threshold_ms'][$name] ?? 0);
    }

    /**
     * @return array{project: string, env: string, service: string}
     */
    public function identityLabels(): array
    {
        return [
            'project' => $this->project(),
            'env' => $this->env(),
            'service' => $this->service(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'enabled' => true,
            'project' => 'default',
            'env' => 'local',
            'service' => 'hyperf',
            'metrics' => [
                'enabled' => true,
                'path' => '/metrics',
            ],
            'trace' => [
                'headers' => [
                    'trace_id' => 'X-Netfly-Trace-Id',
                    'span_id' => 'X-Netfly-Span-Id',
                    'parent_span_id' => 'X-Netfly-Parent-Span-Id',
                ],
            ],
            'logging' => [
                'enabled' => true,
                'path' => $this->basePath() . '/runtime/logs/netfly-observability.log',
                'remote' => [
                    'enabled' => false,
                    'driver' => 'tcp',
                    'host' => '127.0.0.1',
                    'port' => 9000,
                    'url' => 'http://127.0.0.1:9000/logs',
                    'timeout_ms' => 50,
                    'headers' => [],
                ],
            ],
            'http' => ['enabled' => true],
            'mysql' => ['enabled' => true],
            'redis' => ['enabled' => true],
            'rabbitmq' => ['enabled' => true],
            'slow_threshold_ms' => [
                'http' => 1000,
                'mysql' => 200,
                'redis' => 100,
                'rabbitmq' => 500,
            ],
        ];
    }

    private function stringValue(string $key, string $default): string
    {
        $value = $this->config[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param list<string> $path
     */
    private function stringValueFromPath(array $path, string $default): string
    {
        $value = $this->config;
        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    private function basePath(): string
    {
        return defined('BASE_PATH') ? (string) constant('BASE_PATH') : getcwd();
    }
}
