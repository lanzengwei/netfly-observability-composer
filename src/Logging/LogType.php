<?php

declare(strict_types=1);

namespace Netfly\Observability\Logging;

final class LogType
{
    public const REQUEST = 'request';
    public const ERROR = 'error';
    public const SLOW = 'slow';
    public const DB = 'db';
    public const REDIS = 'redis';
    public const AMQP = 'amqp';
    public const APP = 'app';
}
