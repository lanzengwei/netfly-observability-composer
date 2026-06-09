<?php

declare(strict_types=1);

namespace Netfly\Observability\Metrics;

final class PrometheusRenderer
{
    /**
     * @param iterable<MetricSample> $samples
     */
    public function render(iterable $samples): string
    {
        $lines = [];
        $types = [];

        foreach ($samples as $sample) {
            if (! isset($types[$sample->name])) {
                $types[$sample->name] = true;
                $lines[] = sprintf('# TYPE %s %s', $sample->name, $sample->type);
            }

            $lines[] = sprintf(
                '%s%s %s',
                $sample->name,
                $this->renderLabels($sample->labels),
                $this->renderValue($sample->value)
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);
        $rendered = [];

        foreach ($labels as $key => $value) {
            $rendered[] = sprintf('%s="%s"', $key, $this->escapeLabelValue($value));
        }

        return '{' . implode(',', $rendered) . '}';
    }

    private function escapeLabelValue(string $value): string
    {
        return str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\\"'], $value);
    }

    private function renderValue(float|int $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.') ?: '0';
    }
}
