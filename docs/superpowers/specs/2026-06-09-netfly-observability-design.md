# netfly-observability Design

Date: 2026-06-09

## Goal

Build `netfly/observability`, a Hyperf 3.x Composer package for PHP 8.1+ and Swoole 5.x that collects PHP/Swoole, HTTP, MySQL, Redis, and RabbitMQ observability data. The package exposes Prometheus metrics and emits structured JSON logs that Promtail can ship to Loki. The repository also includes a runnable Hyperf demo system that continuously generates traffic and dependency calls, plus a Docker Compose observability stack with pre-provisioned Grafana dashboards.

The package collects and reports production application data. The demo application is responsible for generating sample data.

## Architecture

Use a Hyperf-native integration model.

The package registers itself through `ConfigProvider` and provides:

- HTTP middleware to generate or propagate `trace_id`, store it in coroutine context, and record request metrics and request logs.
- Metrics services and a `/metrics` endpoint for Prometheus scraping.
- Structured log formatter and helper services that add common fields to all observability logs.
- Collectors for MySQL, Redis, and RabbitMQ using Hyperf events, listeners, and AOP aspects where Hyperf does not expose enough event detail.
- Runtime collectors for PHP memory and Swoole coroutine/server stats.
- Configuration for project identity, feature switches, and slow-operation thresholds.

The storage and query path is:

1. Hyperf application runs with `netfly/observability`.
2. Prometheus scrapes the application `/metrics` endpoint.
3. The application writes JSON logs to files/stdout.
4. Promtail tails JSON logs and pushes them to Loki.
5. Grafana uses Prometheus and Loki data sources for dashboards and Explore queries.

## Package Scope

Composer package name: `netfly/observability`.

Root repository name: `netfly-observability`.

Target runtime:

- PHP 8.1+
- Hyperf 3.x
- Swoole 5.x

Planned package structure:

```text
composer.json
config/observability.php
src/ConfigProvider.php
src/Context/TraceContext.php
src/Middleware/TraceMiddleware.php
src/Metrics/*
src/Logging/*
src/Collector/*
src/Aspect/*
src/Listener/*
src/Command/*
tests/*
```

The package should be usable by real Hyperf projects and by the local demo app through a Composer path repository.

## Configuration

The package configuration is published as `config/observability.php`.

Required identity fields:

- `project`: project or tenant identifier used for filtering.
- `env`: environment name, such as `local`, `dev`, `staging`, or `prod`.
- `service`: service name inside the project.

Switches:

- `enabled`: master switch.
- `metrics.enabled`: enables `/metrics` and metric collection.
- `logging.enabled`: enables observability JSON logging.
- `http.enabled`: enables HTTP middleware collection.
- `mysql.enabled`: enables MySQL collection.
- `redis.enabled`: enables Redis collection.
- `rabbitmq.enabled`: enables RabbitMQ collection.

Slow thresholds:

- `slow_threshold_ms.http`
- `slow_threshold_ms.mysql`
- `slow_threshold_ms.redis`
- `slow_threshold_ms.rabbitmq`

Behavior:

- When `enabled` is false, no collectors emit metrics or observability logs.
- When a specific collector switch is false, only that collector is suppressed.
- When `metrics.enabled` is false, metrics are not registered and the metrics route is unavailable or returns no package metrics.
- When `logging.enabled` is false, the package does not alter log formatting or write observability logs.

## Trace Model

Each inbound HTTP request gets a `trace_id`.

Rules:

- Prefer an inbound `X-Trace-Id` header when present and valid.
- Generate a new trace ID when the header is missing or invalid.
- Store `trace_id` in Hyperf coroutine context for the lifetime of the request.
- Include `trace_id` in HTTP response headers.
- Include `trace_id` in all request, error, slow, database, Redis, RabbitMQ, and application observability logs produced during the request.

The initial implementation uses lightweight trace correlation, not a full distributed tracing backend. Optional `span_id` and `parent_span_id` fields may be emitted by dependency collectors so a single-request chain can be ordered in logs.

## Metrics Model

Metrics use the `netfly_` prefix.

Core metrics:

- `netfly_http_requests_total`
- `netfly_http_request_duration_seconds_bucket`
- `netfly_http_request_duration_seconds_count`
- `netfly_http_request_duration_seconds_sum`
- `netfly_db_queries_total`
- `netfly_db_query_duration_seconds_bucket`
- `netfly_redis_commands_total`
- `netfly_redis_command_duration_seconds_bucket`
- `netfly_amqp_publish_total`
- `netfly_amqp_consume_total`
- `netfly_amqp_duration_seconds_bucket`
- `netfly_php_memory_usage_bytes`
- `netfly_php_memory_peak_bytes`
- `netfly_swoole_coroutine_num`

Common labels:

- `project`
- `env`
- `service`

HTTP labels:

- `method`
- `route`
- `status`

MySQL labels:

- `connection`
- `database`
- `operation`
- `result`

Redis labels:

- `pool`
- `command`
- `result`

RabbitMQ labels:

- `exchange`
- `queue`
- `routing_key`
- `result`

High-cardinality values such as raw SQL, Redis keys, payloads, exception messages, and trace IDs are not metric labels.

## Logging Model

Observability logs are JSON lines.

Loki labels are limited to low-cardinality fields:

- `project`
- `env`
- `service`
- `level`
- `log_type`

`trace_id` is stored in the JSON body, not as a Loki label, to avoid label cardinality problems.

Common JSON fields:

- `timestamp`
- `project`
- `env`
- `service`
- `level`
- `log_type`
- `trace_id`
- `span_id`
- `parent_span_id`
- `message`
- `duration_ms`
- `context`

Supported `log_type` values:

- `request`
- `error`
- `slow`
- `db`
- `redis`
- `amqp`
- `app`

Request logs include method, URI, route, status, client IP, user agent, and duration.

Error logs include exception class, message, code, file, line, and a trimmed stack trace.

Slow logs include `duration_ms`, `threshold_ms`, component, operation, and relevant safe context.

Dependency logs include command or operation metadata. Raw SQL and payload data are sanitized or truncated before logging.

## Grafana Experience

Grafana is provisioned automatically with Prometheus and Loki data sources.

Dashboards include:

- Overview: request rate, latency percentiles, error rate, PHP memory, Swoole coroutine count.
- HTTP: route-level requests, latency, status codes, slow requests.
- MySQL: query count, duration, errors, slow queries.
- Redis: command count, duration, errors, slow commands.
- RabbitMQ: publish/consume count, duration, errors.
- Logs: request logs, error logs, slow logs, dependency logs, and trace lookup.

Dashboard variables:

- `project`
- `env`
- `service`
- `log_type`
- `trace_id`
- `slow_ms`

Example Loki queries:

```logql
{project="$project", service="$service", log_type="request"} | json | trace_id="$trace_id"
{project="$project", log_type="error"} | json
{project="$project", log_type="slow"} | json | duration_ms > $slow_ms
{project="$project", log_type=~"db|redis|amqp"} | json | trace_id="$trace_id"
```

## Demo System

The repository includes `demo/`, a Hyperf application that imports the local package using Composer path repository configuration.

Demo services in Docker Compose:

- `demo-api`: Hyperf demo application.
- `mysql`: relational dependency.
- `redis`: cache dependency.
- `rabbitmq`: message queue dependency.
- `prometheus`: metrics storage and scraper.
- `loki`: log storage.
- `promtail`: log shipper.
- `grafana`: dashboards and Explore UI.

The demo app continuously generates data through a Hyperf process:

- HTTP requests against demo routes.
- MySQL SELECT, INSERT, and intentionally slow queries.
- Redis GET, SET, and intentionally slow commands.
- RabbitMQ publish and consume flows.
- Random controlled exceptions.
- Random slow request paths.

All generated flows preserve `trace_id` so Grafana can query the full chain.

## Docker Compose

The root `docker-compose.yml` provides the full stack.

Prometheus configuration scrapes `demo-api` at `/metrics`.

Promtail reads demo JSON logs and maps these labels:

- `project`
- `env`
- `service`
- `level`
- `log_type`

Grafana provisioning creates:

- Prometheus data source.
- Loki data source.
- Netfly observability dashboards.

## Testing and Verification

Implementation should follow test-first behavior for package logic where practical.

Unit tests cover:

- `trace_id` generation, validation, propagation, and response header output.
- JSON log shape and required fields.
- Collector switches suppress collection when disabled.
- Metric names and labels.
- Slow threshold classification.
- Sanitization or truncation for sensitive or high-cardinality fields.

Integration-style tests cover:

- `/metrics` output contains expected samples when enabled.
- `/metrics` does not expose package metrics when metrics are disabled.
- Request, error, slow, db, redis, and amqp log entries include common identity fields.
- Docker Compose configuration is valid.

Verification commands:

```bash
composer validate --strict
composer test
composer analyse
docker compose config
```

If Docker is available locally, also run:

```bash
docker compose up -d --build
```

Then verify:

- `demo-api` is healthy.
- `/metrics` exposes `netfly_` metrics.
- demo logs contain JSON lines with `trace_id`, `project`, `service`, and `log_type`.
- Grafana provisioning files are present and mounted into Grafana.

## Non-Goals

This first version does not implement a full OpenTelemetry collector pipeline.

This first version does not provide cross-service distributed tracing storage. It provides trace-correlated logs for a request chain inside the Hyperf service and the demo flow.

This first version does not label logs in Loki by `trace_id`; `trace_id` remains a JSON field queried through LogQL pipelines.

## Acceptance Criteria

- A new Hyperf project can install and enable `netfly/observability`.
- Project, environment, and service identity appear on metrics and logs.
- Users can turn the package or individual collectors on and off.
- Prometheus can scrape package metrics from `/metrics`.
- Loki can store structured logs shipped by Promtail.
- Grafana can filter by project and service.
- Grafana can query all logs for a specific `trace_id`.
- Grafana can show request logs, error logs, slow logs, and dependency logs.
- The demo system continuously generates HTTP, MySQL, Redis, RabbitMQ, error, and slow-log data.
- Code validation, tests, static analysis, and Docker Compose configuration checks are run before completion is claimed.
