<?php

declare(strict_types=1);

namespace Netfly\Observability\Aspect;

final class MysqlAspect
{
    /**
     * Marker class for Hyperf AOP registration in applications that enable database instrumentation.
     */
    public array $classes = [];
}
