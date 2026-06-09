<?php

declare(strict_types=1);

namespace Netfly\Observability\Tests\Unit;

use Netfly\Observability\Logging\LogSanitizer;
use PHPUnit\Framework\TestCase;

final class LogSanitizerTest extends TestCase
{
    public function test_redacts_sensitive_keys_recursively(): void
    {
        $sanitizer = new LogSanitizer();

        $context = $sanitizer->sanitize([
            'authorization' => 'Bearer abc',
            'nested' => [
                'password' => 'secret',
                'safe' => 'value',
            ],
        ]);

        self::assertSame('[redacted]', $context['authorization']);
        self::assertSame('[redacted]', $context['nested']['password']);
        self::assertSame('value', $context['nested']['safe']);
    }

    public function test_truncates_long_strings(): void
    {
        $sanitizer = new LogSanitizer(10);

        $context = $sanitizer->sanitize(['sql' => str_repeat('a', 20)]);

        self::assertSame(str_repeat('a', 10) . '...', $context['sql']);
    }
}
