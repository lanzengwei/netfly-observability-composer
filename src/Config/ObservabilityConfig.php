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

    public function collectorEnabled(string $name): bool
    {
        return $this->enabled() && (bool) ($this->config[$name]['enabled'] ?? true);
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
            'logging' => [
                'enabled' => true,
                'path' => $this->basePath() . '/runtime/logs/netfly-observability.log',
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

    private function basePath(): string
    {
        return defined('BASE_PATH') ? (string) constant('BASE_PATH') : getcwd();
    }
}
