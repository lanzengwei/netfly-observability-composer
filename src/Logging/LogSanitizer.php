<?php

declare(strict_types=1);

namespace Netfly\Observability\Logging;

final class LogSanitizer
{
    public function __construct(private readonly int $maxStringLength = 2048)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if (preg_match('/password|token|secret|authorization/i', $key) === 1) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeValue((string) $childKey, $childValue);
            }

            return $sanitized;
        }

        if (is_string($value) && strlen($value) > $this->maxStringLength) {
            return substr($value, 0, $this->maxStringLength) . '...';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : $value::class;
        }

        return $value;
    }
}
