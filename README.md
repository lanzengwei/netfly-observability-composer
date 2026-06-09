# netfly-observability

Hyperf observability package for Prometheus metrics and Loki structured logs.

## What It Collects

- PHP memory and Swoole coroutine metrics.
- HTTP request count, status, duration, request logs, slow request logs.
- MySQL query count, duration, result, slow query logs.
- Redis command count, duration, result, slow command logs.
- RabbitMQ publish/consume count, duration, result, slow AMQP logs.
- Structured JSON logs with `trace_id`, `project`, `env`, `service`, `level`, and `log_type`.

## Install

```bash
composer require netfly/observability
```

Publish the config in a Hyperf project:

```bash
php bin/hyperf.php vendor:publish netfly/observability
```

## Configuration

The published file is `config/autoload/observability.php`.

Important environment variables:

```dotenv
NETFLY_OBSERVABILITY_ENABLED=true
NETFLY_OBSERVABILITY_PROJECT=demo-shop
APP_ENV=local
NETFLY_OBSERVABILITY_SERVICE=demo-api
NETFLY_OBSERVABILITY_METRICS_ENABLED=true
NETFLY_OBSERVABILITY_LOGGING_ENABLED=true
NETFLY_OBSERVABILITY_HTTP_ENABLED=true
NETFLY_OBSERVABILITY_MYSQL_ENABLED=true
NETFLY_OBSERVABILITY_REDIS_ENABLED=true
NETFLY_OBSERVABILITY_RABBITMQ_ENABLED=true
NETFLY_OBSERVABILITY_LOG_PATH=/app/runtime/logs/netfly-observability.log
```

Switch behavior:

- `enabled=false`: disables all package collection and observability logging.
- `metrics.enabled=false`: disables package metrics output.
- `logging.enabled=false`: disables JSON observability log writes.
- `http/mysql/redis/rabbitmq.enabled=false`: disables one collector.

## Metrics

Metrics use the `netfly_` prefix and include common labels:

- `project`
- `env`
- `service`

Core metric names:

- `netfly_http_requests_total`
- `netfly_http_request_duration_seconds_bucket`
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

## Logs

Logs are JSON lines. Loki labels should stay low-cardinality:

- `project`
- `env`
- `service`
- `level`
- `log_type`

`trace_id` is a JSON field, not a Loki label.

Supported `log_type` values:

- `request`
- `error`
- `slow`
- `db`
- `redis`
- `amqp`
- `app`

Useful LogQL examples:

```logql
{project="$project", service="$service", log_type="request"} | json | trace_id="$trace_id"
{project="$project", log_type="error"} | json
{project="$project", log_type="slow"} | json | duration_ms > $slow_ms
{project="$project", log_type=~"db|redis|amqp"} | json | trace_id="$trace_id"
```

## Demo Stack

Start the full local stack:

```bash
docker compose up -d --build
```

Services:

- Demo API: `http://localhost:19501`
- Metrics: `http://localhost:19501/metrics`
- Prometheus: `http://localhost:19090`
- Grafana: `http://localhost:13000`

Grafana credentials:

- User: `admin`
- Password: `admin`

The demo process continuously writes JSON logs and generates request, dependency, error, and slow-log events. Prometheus scrapes `/metrics`; Promtail ships logs to Loki; Grafana provisions a Netfly dashboard automatically.

## Development Verification

The local machine may not have PHP 8.1. These commands run verification in Docker:

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app/demo composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```

On Windows PowerShell:

```powershell
$pwdPath = (Get-Location).Path
docker run --rm -v "${pwdPath}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${pwdPath}:/app" -w /app/demo composer:2 composer validate --strict
docker run --rm -v "${pwdPath}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${pwdPath}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```
