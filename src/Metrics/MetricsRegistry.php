<?php

declare(strict_types=1);

namespace Netfly\Observability\Metrics;

final class MetricsRegistry
{
    private const DEFAULT_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    /**
     * @var array<string, string>
     */
    private array $commonLabels;

    /**
     * @var array<string, array{name: string, type: string, labels: array<string, string>, value: float|int}>
     */
    private array $samples = [];

    /**
     * @param array<string, string> $commonLabels
     */
    public function __construct(array $commonLabels = [])
    {
        $this->commonLabels = $this->stringLabels($commonLabels);
    }

    /**
     * @param array<string, mixed> $labels
     */
    public function counter(string $name, array $labels = [], float|int $amount = 1): void
    {
        $sampleLabels = $this->mergeLabels($labels);
        $key = $this->sampleKey($name, $sampleLabels);

        if (! isset($this->samples[$key])) {
            $this->samples[$key] = [
                'name' => $name,
                'type' => 'counter',
                'labels' => $sampleLabels,
                'value' => 0,
            ];
        }

        $this->samples[$key]['value'] += $amount;
    }

    /**
     * @param array<string, mixed> $labels
     * @param list<float> $buckets
     */
    public function histogram(string $name, array $labels, float $value, array $buckets = self::DEFAULT_BUCKETS): void
    {
        sort($buckets);

        foreach ($buckets as $bucket) {
            $bucketLabels = $labels;
            $bucketLabels['le'] = $this->formatNumber($bucket);
            $this->counter($name . '_bucket', $bucketLabels, $value <= $bucket ? 1 : 0);
        }

        $infLabels = $labels;
        $infLabels['le'] = '+Inf';
        $this->counter($name . '_bucket', $infLabels, 1);
        $this->counter($name . '_count', $labels, 1);
        $this->gauge($name . '_sum', $labels, $this->currentValue($name . '_sum', $this->mergeLabels($labels)) + $value);
    }

    /**
     * @param array<string, mixed> $labels
     */
    public function gauge(string $name, array $labels, float|int $value): void
    {
        $sampleLabels = $this->mergeLabels($labels);
        $this->samples[$this->sampleKey($name, $sampleLabels)] = [
            'name' => $name,
            'type' => 'gauge',
            'labels' => $sampleLabels,
            'value' => $value,
        ];
    }

    /**
     * @return list<MetricSample>
     */
    public function samples(): array
    {
        return array_map(
            static fn (array $sample): MetricSample => new MetricSample(
                $sample['name'],
                $sample['type'],
                $sample['labels'],
                $sample['value']
            ),
            array_values($this->samples)
        );
    }

    /**
     * @param array<string, mixed> $labels
     * @return array<string, string>
     */
    private function mergeLabels(array $labels): array
    {
        return $this->stringLabels(array_merge($this->commonLabels, $labels));
    }

    /**
     * @param array<string, mixed> $labels
     * @return array<string, string>
     */
    private function stringLabels(array $labels): array
    {
        $normalized = [];

        foreach ($labels as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, string> $labels
     */
    private function sampleKey(string $name, array $labels): string
    {
        return $name . ':' . json_encode($labels, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string> $labels
     */
    private function currentValue(string $name, array $labels): float|int
    {
        $key = $this->sampleKey($name, $labels);

        return $this->samples[$key]['value'] ?? 0;
    }

    private function formatNumber(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
