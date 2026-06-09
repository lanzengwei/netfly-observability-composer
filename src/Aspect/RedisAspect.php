<?php

declare(strict_types=1);

namespace Netfly\Observability\Aspect;

final class RedisAspect
{
    /**
     * Marker class for Hyperf AOP registration in applications that enable Redis instrumentation.
     */
    public array $classes = [];
}
