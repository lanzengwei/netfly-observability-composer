# netfly-observability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Hyperf 3.x Composer package named `netfly/observability` with metrics, structured logs, trace correlation, Docker observability stack, Grafana dashboards, and a live demo traffic generator.

**Architecture:** Implement a Hyperf-native package using `ConfigProvider`, middleware, collectors, metrics registry, and JSON logging services. The demo Hyperf app imports the local package through Composer path repository and emits logs scraped by Promtail while Prometheus scrapes `/metrics`.

**Tech Stack:** PHP 8.1+, Hyperf 3.x, Swoole 5.x, PHPUnit, PHPStan, Prometheus text exposition format, Docker Compose, Prometheus, Loki, Promtail, Grafana, MySQL, Redis, RabbitMQ.

---

## File Structure

- `composer.json`: root Composer package metadata, dependencies, scripts, autoload.
- `phpunit.xml`: PHPUnit configuration.
- `phpstan.neon`: PHPStan configuration.
- `config/observability.php`: publishable package configuration.
- `src/ConfigProvider.php`: Hyperf package registration.
- `src/Contract/*.php`: small interfaces for config, metrics, logging, and trace services.
- `src/Config/ObservabilityConfig.php`: normalized configuration and switch behavior.
- `src/Context/TraceContext.php`: coroutine-safe trace storage.
- `src/Trace/TraceIdGenerator.php`: trace ID generation and validation.
- `src/Logging/*.php`: JSON log record building, sanitization, and file writer.
- `src/Metrics/*.php`: in-memory metric registry and Prometheus text renderer.
- `src/Middleware/TraceMiddleware.php`: HTTP trace propagation and request collection.
- `src/Controller/MetricsController.php`: `/metrics` response controller.
- `src/Collector/*.php`: HTTP, MySQL, Redis, RabbitMQ, PHP/Swoole collector services.
- `src/Aspect/*.php`: dependency timing hooks for Hyperf database, Redis, and AMQP classes.
- `src/Listener/*.php`: error and runtime listeners where Hyperf events are suitable.
- `tests/Unit/*.php`: unit tests for trace, config, logging, metrics, and collectors.
- `tests/Feature/*.php`: feature-style tests for middleware and `/metrics` rendering.
- `demo/*`: Hyperf demo application, traffic generator, controllers, dependencies.
- `docker-compose.yml`: full stack.
- `docker/*`: Prometheus, Loki, Promtail, Grafana provisioning and dashboard files.
- `README.md`: installation, configuration, demo, and query instructions.

## Task 1: Root Package Skeleton

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `phpstan.neon`
- Create: `src/ConfigProvider.php`
- Create: `config/observability.php`
- Create: `tests/bootstrap.php`

- [ ] **Step 1: Write package metadata and scripts**

Create `composer.json` with PHP 8.1+, Hyperf 3.x components, PHPUnit, PHPStan, and scripts:

```json
{
  "name": "netfly/observability",
  "description": "Hyperf observability package with Prometheus metrics and Loki structured logs.",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "Netfly\\Observability\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Netfly\\Observability\\Tests\\": "tests/"
    },
    "files": [
      "tests/bootstrap.php"
    ]
  },
  "require": {
    "php": "^8.1",
    "ext-json": "*",
    "hyperf/contract": "^3.1",
    "hyperf/context": "^3.1",
    "hyperf/di": "^3.1",
    "hyperf/http-server": "^3.1",
    "hyperf/logger": "^3.1",
    "hyperf/utils": "^3.1",
    "psr/http-message": "^1.0|^2.0",
    "psr/http-server-middleware": "^1.0",
    "psr/log": "^2.0|^3.0"
  },
  "require-dev": {
    "mockery/mockery": "^1.6",
    "phpstan/phpstan": "^1.12",
    "phpunit/phpunit": "^9.6"
  },
  "extra": {
    "hyperf": {
      "config": "Netfly\\Observability\\ConfigProvider"
    }
  },
  "scripts": {
    "test": "phpunit --colors=always",
    "analyse": "phpstan analyse --memory-limit=512M",
    "check": [
      "@test",
      "@analyse"
    ]
  },
  "config": {
    "sort-packages": true
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

- [ ] **Step 2: Add package config provider**

Create `src/ConfigProvider.php` returning dependencies, annotations scan, publish config, middleware, and route registration placeholders:

```php
<?php

declare(strict_types=1);

namespace Netfly\Observability;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'annotations' => [
                'scan' => [
                    'paths' => [__DIR__],
                ],
            ],
            'publish' => [
                [
                    'id' => 'observability',
                    'description' => 'Netfly observability config.',
                    'source' => __DIR__ . '/../config/observability.php',
                    'destination' => BASE_PATH . '/config/autoload/observability.php',
                ],
            ],
        ];
    }
}
```

- [ ] **Step 3: Add default config**

Create `config/observability.php` with identity, switches, thresholds, metrics path, and log path:

```php
<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('NETFLY_OBSERVABILITY_ENABLED', true),
    'project' => env('NETFLY_OBSERVABILITY_PROJECT', 'default'),
    'env' => env('APP_ENV', 'local'),
    'service' => env('NETFLY_OBSERVABILITY_SERVICE', 'hyperf'),
    'metrics' => [
        'enabled' => (bool) env('NETFLY_OBSERVABILITY_METRICS_ENABLED', true),
        'path' => env('NETFLY_OBSERVABILITY_METRICS_PATH', '/metrics'),
    ],
    'logging' => [
        'enabled' => (bool) env('NETFLY_OBSERVABILITY_LOGGING_ENABLED', true),
        'path' => env('NETFLY_OBSERVABILITY_LOG_PATH', BASE_PATH . '/runtime/logs/netfly-observability.log'),
    ],
    'http' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_HTTP_ENABLED', true)],
    'mysql' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_MYSQL_ENABLED', true)],
    'redis' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_REDIS_ENABLED', true)],
    'rabbitmq' => ['enabled' => (bool) env('NETFLY_OBSERVABILITY_RABBITMQ_ENABLED', true)],
    'slow_threshold_ms' => [
        'http' => (int) env('NETFLY_OBSERVABILITY_SLOW_HTTP_MS', 1000),
        'mysql' => (int) env('NETFLY_OBSERVABILITY_SLOW_MYSQL_MS', 200),
        'redis' => (int) env('NETFLY_OBSERVABILITY_SLOW_REDIS_MS', 100),
        'rabbitmq' => (int) env('NETFLY_OBSERVABILITY_SLOW_RABBITMQ_MS', 500),
    ],
];
```

- [ ] **Step 4: Run validation**

Run in PHP 8.1 container:

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer validate --strict
```

Expected: `./composer.json is valid`.

## Task 2: Configuration and Trace Core

**Files:**
- Create: `src/Config/ObservabilityConfig.php`
- Create: `src/Context/TraceContext.php`
- Create: `src/Trace/TraceIdGenerator.php`
- Test: `tests/Unit/ObservabilityConfigTest.php`
- Test: `tests/Unit/TraceIdGeneratorTest.php`
- Test: `tests/Unit/TraceContextTest.php`

- [ ] **Step 1: Write failing config tests**

Test normalized defaults, identity fields, master switch, and collector switches:

```php
public function test_master_switch_disables_everything(): void
{
    $config = new ObservabilityConfig(['enabled' => false]);
    self::assertFalse($config->enabled());
    self::assertFalse($config->metricsEnabled());
    self::assertFalse($config->loggingEnabled());
    self::assertFalse($config->collectorEnabled('http'));
}
```

- [ ] **Step 2: Implement config service**

Implement `ObservabilityConfig` with `enabled()`, `project()`, `env()`, `service()`, `metricsPath()`, `logPath()`, `metricsEnabled()`, `loggingEnabled()`, `collectorEnabled(string $name)`, and `slowThresholdMs(string $name)`.

- [ ] **Step 3: Write failing trace tests**

Test inbound validation and generated IDs:

```php
public function test_uses_valid_inbound_trace_id(): void
{
    $generator = new TraceIdGenerator();
    self::assertSame('abc123def456', $generator->fromHeader('abc123def456'));
}
```

- [ ] **Step 4: Implement trace services**

`TraceIdGenerator` accepts 8 to 128 characters containing letters, numbers, dot, underscore, and dash. Invalid input generates `bin2hex(random_bytes(16))`. `TraceContext` stores and retrieves `netfly.trace_id` using Hyperf Context when available, with an in-process fallback for tests.

- [ ] **Step 5: Run tests**

Run:

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit tests/Unit/ObservabilityConfigTest.php tests/Unit/TraceIdGeneratorTest.php tests/Unit/TraceContextTest.php
```

Expected: all tests pass.

## Task 3: Metrics Registry and Renderer

**Files:**
- Create: `src/Metrics/MetricSample.php`
- Create: `src/Metrics/MetricsRegistry.php`
- Create: `src/Metrics/PrometheusRenderer.php`
- Create: `src/Collector/RuntimeCollector.php`
- Test: `tests/Unit/MetricsRegistryTest.php`
- Test: `tests/Unit/PrometheusRendererTest.php`

- [ ] **Step 1: Write failing metric tests**

Test counters, histograms, common labels, and escaping:

```php
public function test_counter_is_rendered_with_common_labels(): void
{
    $registry = new MetricsRegistry(['project' => 'shop', 'env' => 'local', 'service' => 'api']);
    $registry->counter('netfly_http_requests_total', ['method' => 'GET', 'status' => '200'], 1);
    $output = (new PrometheusRenderer())->render($registry->samples());
    self::assertStringContainsString('netfly_http_requests_total{project="shop",env="local",service="api",method="GET",status="200"} 1', $output);
}
```

- [ ] **Step 2: Implement metrics registry**

Implement counters and histograms as in-memory cumulative samples. Histograms create `_bucket`, `_sum`, and `_count` samples with default buckets `[0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]`.

- [ ] **Step 3: Implement renderer**

Render Prometheus text exposition with `# TYPE` lines, sorted labels, escaped label values, and final newline.

- [ ] **Step 4: Add runtime collector**

Collect `memory_get_usage(true)`, `memory_get_peak_usage(true)`, and `Swoole\Coroutine::stats()['coroutine_num']` when the class exists.

- [ ] **Step 5: Run tests**

Run:

```bash
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit tests/Unit/MetricsRegistryTest.php tests/Unit/PrometheusRendererTest.php
```

Expected: all tests pass.

## Task 4: Structured Logging

**Files:**
- Create: `src/Logging/LogType.php`
- Create: `src/Logging/JsonLogFormatter.php`
- Create: `src/Logging/LogSanitizer.php`
- Create: `src/Logging/ObservabilityLogger.php`
- Test: `tests/Unit/JsonLogFormatterTest.php`
- Test: `tests/Unit/LogSanitizerTest.php`

- [ ] **Step 1: Write failing formatter tests**

Test common fields and trace ID:

```php
public function test_formats_required_json_fields(): void
{
    $formatter = new JsonLogFormatter(['project' => 'shop', 'env' => 'local', 'service' => 'api']);
    $json = $formatter->format('info', 'request', 'ok', 'trace-1', ['duration_ms' => 12.3]);
    $data = json_decode($json, true);
    self::assertSame('shop', $data['project']);
    self::assertSame('trace-1', $data['trace_id']);
    self::assertSame('request', $data['log_type']);
}
```

- [ ] **Step 2: Implement formatter and sanitizer**

Formatter emits one JSON object per line with `timestamp`, identity, `level`, `log_type`, `trace_id`, `message`, and `context`. Sanitizer truncates strings to 2048 characters and replaces sensitive keys matching `password`, `token`, `secret`, or `authorization` with `[redacted]`.

- [ ] **Step 3: Implement file logger**

`ObservabilityLogger` writes formatted JSON lines to configured path, creates the directory when missing, and no-ops when logging is disabled.

- [ ] **Step 4: Run tests**

Run:

```bash
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit tests/Unit/JsonLogFormatterTest.php tests/Unit/LogSanitizerTest.php
```

Expected: all tests pass.

## Task 5: HTTP Middleware and Metrics Endpoint

**Files:**
- Create: `src/Middleware/TraceMiddleware.php`
- Create: `src/Controller/MetricsController.php`
- Create: `src/Collector/HttpCollector.php`
- Modify: `src/ConfigProvider.php`
- Test: `tests/Feature/TraceMiddlewareTest.php`
- Test: `tests/Feature/MetricsControllerTest.php`

- [ ] **Step 1: Write failing middleware tests**

Use small PSR-7 and handler stubs to assert response header, metric sample, request log, and disabled behavior.

- [ ] **Step 2: Implement middleware**

Middleware gets inbound `X-Trace-Id`, stores trace context, measures duration using `microtime(true)`, delegates to handler, records HTTP metrics/logs when enabled, and returns response with `X-Trace-Id`.

- [ ] **Step 3: Implement metrics controller**

Controller returns `text/plain; version=0.0.4` Prometheus output. When metrics are disabled, it returns an empty text response.

- [ ] **Step 4: Wire provider**

Update `ConfigProvider` dependencies for config, trace generator, trace context, metrics registry, renderer, formatter, logger, collectors, middleware, and controller.

- [ ] **Step 5: Run feature tests**

Run:

```bash
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit tests/Feature
```

Expected: all tests pass.

## Task 6: Dependency Collectors

**Files:**
- Create: `src/Collector/DependencyResult.php`
- Create: `src/Collector/MysqlCollector.php`
- Create: `src/Collector/RedisCollector.php`
- Create: `src/Collector/RabbitMqCollector.php`
- Create: `src/Aspect/MysqlAspect.php`
- Create: `src/Aspect/RedisAspect.php`
- Create: `src/Aspect/RabbitMqAspect.php`
- Test: `tests/Unit/DependencyCollectorTest.php`

- [ ] **Step 1: Write failing collector tests**

Assert success metrics, error metrics, slow log classification, trace ID inclusion, and collector switches.

- [ ] **Step 2: Implement shared result object**

`DependencyResult` contains component, operation, peer labels, duration, result, error class, and sanitized context.

- [ ] **Step 3: Implement collectors**

Each collector records count and duration metrics, emits dependency logs, and emits a `slow` log when duration exceeds its configured threshold.

- [ ] **Step 4: Implement aspects**

Aspects wrap Hyperf database, Redis, and AMQP calls when those packages are present. They call collectors in `finally` and avoid hard dependency failures by checking class existence.

- [ ] **Step 5: Run tests**

Run:

```bash
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit tests/Unit/DependencyCollectorTest.php
```

Expected: all tests pass.

## Task 7: Demo Hyperf App

**Files:**
- Create: `demo/composer.json`
- Create: `demo/Dockerfile`
- Create: `demo/bin/hyperf.php`
- Create: `demo/config/autoload/*.php`
- Create: `demo/app/Controller/TrafficController.php`
- Create: `demo/app/Process/TrafficGeneratorProcess.php`
- Create: `demo/app/Service/TrafficService.php`
- Create: `demo/migrations/001_create_orders.sql`

- [ ] **Step 1: Create demo composer app**

Use Hyperf dependencies and local path repository:

```json
{
  "name": "netfly/observability-demo",
  "type": "project",
  "repositories": [
    {"type": "path", "url": "../", "options": {"symlink": true}}
  ],
  "require": {
    "php": "^8.1",
    "hyperf/amqp": "^3.1",
    "hyperf/database": "^3.1",
    "hyperf/db-connection": "^3.1",
    "hyperf/framework": "^3.1",
    "hyperf/http-server": "^3.1",
    "hyperf/process": "^3.1",
    "hyperf/redis": "^3.1",
    "netfly/observability": "*"
  },
  "autoload": {
    "psr-4": {"App\\": "app/"}
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

- [ ] **Step 2: Implement demo routes and service**

Routes include `/demo/orders`, `/demo/cache`, `/demo/publish`, `/demo/error`, `/demo/slow`, and `/metrics`.

- [ ] **Step 3: Implement traffic process**

Every second, call the demo service to generate HTTP-like request logs, database work, Redis work, RabbitMQ work, random slow calls, and controlled exceptions.

- [ ] **Step 4: Build demo image**

Dockerfile uses `hyperf/hyperf:8.1-alpine-v3.18-swoole` or equivalent Hyperf PHP 8.1 Swoole image and runs `composer install`.

## Task 8: Docker Observability Stack

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/prometheus/prometheus.yml`
- Create: `docker/loki/loki.yml`
- Create: `docker/promtail/promtail.yml`
- Create: `docker/grafana/provisioning/datasources/datasources.yml`
- Create: `docker/grafana/provisioning/dashboards/dashboards.yml`
- Create: `docker/grafana/dashboards/netfly-overview.json`

- [ ] **Step 1: Add compose services**

Services: `demo-api`, `mysql`, `redis`, `rabbitmq`, `prometheus`, `loki`, `promtail`, and `grafana`.

- [ ] **Step 2: Add Prometheus config**

Scrape `demo-api:9501/metrics` every 5 seconds.

- [ ] **Step 3: Add Loki and Promtail config**

Promtail tails `/var/log/demo/*.log`, parses JSON, promotes `project`, `env`, `service`, `level`, and `log_type` labels, and sends to `http://loki:3100/loki/api/v1/push`.

- [ ] **Step 4: Add Grafana provisioning**

Provision Prometheus and Loki data sources and dashboard JSON with variables for `project`, `env`, `service`, `trace_id`, `log_type`, and `slow_ms`.

- [ ] **Step 5: Validate compose**

Run:

```bash
docker compose config
```

Expected: exit code 0.

## Task 9: Documentation and Final Verification

**Files:**
- Create: `README.md`
- Modify: `docs/superpowers/specs/2026-06-09-netfly-observability-design.md` only if implementation discovers a necessary correction.

- [ ] **Step 1: Write README**

Document installation, config publishing, switches, metric names, log fields, LogQL examples, Docker demo startup, Grafana URLs, and troubleshooting.

- [ ] **Step 2: Run complete verification**

Run:

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```

Expected: all commands exit 0.

- [ ] **Step 3: Run full demo when Docker image build succeeds**

Run:

```bash
docker compose up -d --build
```

Verify `http://localhost:9501/metrics` contains `netfly_` metrics and Grafana is available at `http://localhost:3000`.

- [ ] **Step 4: Commit final implementation**

Commit in logical chunks:

```bash
git add .
git commit -m "feat: build netfly observability package"
```

## Self-Review

Spec coverage:

- Package identity and Hyperf 3.x target are covered by Tasks 1 and 7.
- Project filtering, switches, and thresholds are covered by Task 2.
- Trace correlation is covered by Tasks 2, 4, 5, and 6.
- Prometheus metrics are covered by Tasks 3, 5, 6, and 8.
- Loki structured logs are covered by Tasks 4, 6, and 8.
- Grafana dashboards and trace queries are covered by Task 8.
- Demo real-time data generation is covered by Task 7.
- Verification and documentation are covered by Task 9.

Placeholder scan: no deferred implementation placeholders are intentionally left in this plan.

Type consistency: planned service names use `ObservabilityConfig`, `TraceContext`, `TraceIdGenerator`, `MetricsRegistry`, `PrometheusRenderer`, `JsonLogFormatter`, `ObservabilityLogger`, and collector classes consistently across tasks.
