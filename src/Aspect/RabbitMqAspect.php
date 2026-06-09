<?php

declare(strict_types=1);

namespace Netfly\Observability\Aspect;

final class RabbitMqAspect
{
    /**
     * Marker class for Hyperf AOP registration in applications that enable RabbitMQ instrumentation.
     */
    public array $classes = [];
}
