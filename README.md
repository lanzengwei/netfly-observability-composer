# netfly-observability

`netfly/observability` 是一个面向 Hyperf/PHP 服务的可观测性 Composer 包，提供请求链路追踪、结构化 JSON 日志、Prometheus 指标采集，以及一套可本地运行的 Grafana/Loki/Promtail/Prometheus 演示环境。

当前项目的核心目标是让一次请求从 HTTP 入口到 MySQL、Redis、RabbitMQ 等依赖调用都能被清晰追踪：

- 每个请求生成或透传 `trace_id`
- 请求内的依赖调用生成 `span_id`，并记录 `parent_span_id`
- 日志统一携带 `project`、`env`、`service`、`scenario`、`component`、`operation`、`duration_ms`
- 指标通过 `/metrics` 暴露给 Prometheus
- Grafana 按总览、完整链路、MySQL、Redis、RabbitMQ、错误慢日志、原始日志分开查看

## 当前能力

| 能力 | 当前实现 |
|------|----------|
| HTTP 链路追踪 | `TraceMiddleware` 或 demo 内置 kernel 负责生成/透传 `trace_id`，响应头回写链路标识 |
| Span 关联 | `TraceContext` 维护当前请求与 span 栈，依赖 Collector 写入 `span_id` / `parent_span_id` |
| 结构化日志 | `ObservabilityLogger` 写 JSON Lines 文件日志，`JsonLogFormatter` 提升关键字段，`LogSanitizer` 做脱敏与截断 |
| 远程日志 | `RemoteLogSender` 支持 best-effort TCP / HTTP 发送到指定 IP、端口或 URL |
| Prometheus 指标 | `MetricsRegistry` + `PrometheusRenderer` 输出 counter、histogram、gauge |
| 依赖采集 | `MysqlCollector`、`RedisCollector`、`RabbitMqCollector` 显式记录依赖调用指标和日志 |
| 慢操作识别 | HTTP、MySQL、Redis、RabbitMQ 超过阈值时额外写入 `log_type=slow` |
| 演示环境 | Docker Compose 启动 demo-api、MySQL、Redis、RabbitMQ、Prometheus、Loki、Promtail、Grafana |

## 项目目录

```text
.
├── src/                         # Composer 包源码
│   ├── Collector/               # HTTP/MySQL/Redis/RabbitMQ/Runtime 采集器
│   ├── Config/                  # ObservabilityConfig 配置读取与归一化
│   ├── Context/                 # TraceContext 请求链路上下文
│   ├── Controller/              # MetricsController
│   ├── Logging/                 # JSON 日志、脱敏、远程发送
│   ├── Metrics/                 # 指标注册与 Prometheus 渲染
│   ├── Middleware/              # Hyperf TraceMiddleware
│   └── Trace/                   # trace/span 生成与传播
├── config/observability.php     # 发布到 Hyperf 项目的配置模板
├── demo/                        # Hyperf 风格 Swoole 演示应用
│   ├── app/Controller/          # 订单、支付、库存、演示接口
│   ├── app/Http/DemoKernel.php  # demo 路由分发与链路包裹
│   ├── app/Repository/          # PDO MySQL 订单仓库
│   ├── app/Service/             # 业务服务、Redis/RabbitMQ 客户端、流量生成
│   ├── bin/hyperf.php           # demo 启动入口
│   ├── config/autoload/         # Hyperf 风格配置
│   └── migrations/              # MySQL 初始化脚本
├── docker/                      # Prometheus/Loki/Promtail/Grafana 配置
├── tests/                       # PHPUnit 单元测试和结构测试
├── docker-compose.yml           # 本地完整演示栈
├── phpstan.neon                 # 静态分析配置
├── phpunit.xml                  # 测试配置
└── README.md                    # 唯一项目文档
```

`vendor/`、`demo/vendor/`、`.env`、runtime/log/cache、IDE 配置等都是本地生成内容，已通过 `.gitignore` / `.dockerignore` 排除。

## 链路数据模型

日志使用 JSON Lines，每一行是一条结构化事件。关键字段如下：

| 字段 | 说明 |
|------|------|
| `timestamp` | 日志时间 |
| `project` | 项目标识，例如 `demo-shop` |
| `env` | 环境，例如 `local` |
| `service` | 服务名，例如 `demo-api` |
| `level` | 日志级别 |
| `log_type` | `request`、`app`、`db`、`redis`、`amqp`、`slow`、`error` |
| `trace_id` | 一次请求的完整链路 ID |
| `span_id` | 当前调用节点 ID |
| `parent_span_id` | 父调用节点 ID |
| `span_name` | 当前 span 名称 |
| `span_kind` | `server`、`client`、`producer`、`consumer` 等 |
| `scenario` | 业务场景，例如 `order_create`、`payment_callback`、`mixed_traffic` |
| `component` | 组件，例如 `http`、`mysql`、`redis`、`rabbitmq`、`app` |
| `operation` | 操作，例如 `GET`、`insert`、`SET`、`publish` |
| `duration_ms` | 耗时毫秒 |
| `result` | `success` 或 `error` |
| `exception_class` | 异常类型，成功时为空 |

一次典型的订单创建链路：

```text
POST /orders
  ├── http POST /orders
  ├── app create_order
  ├── mysql insert orders
  ├── redis SET order_detail
  ├── rabbitmq publish order.created
  └── http request completed
```

## 指标

所有指标都会带上低基数身份标签：`project`、`env`、`service`。

| 指标 | 类型 | 说明 |
|------|------|------|
| `netfly_http_requests_total` | counter | HTTP 请求数 |
| `netfly_http_request_duration_seconds` | histogram | HTTP 请求耗时 |
| `netfly_db_queries_total` | counter | MySQL 查询数 |
| `netfly_db_query_duration_seconds` | histogram | MySQL 查询耗时 |
| `netfly_redis_commands_total` | counter | Redis 命令数 |
| `netfly_redis_command_duration_seconds` | histogram | Redis 命令耗时 |
| `netfly_amqp_publish_total` | counter | RabbitMQ 发布数 |
| `netfly_amqp_consume_total` | counter | RabbitMQ 消费数 |
| `netfly_amqp_duration_seconds` | histogram | RabbitMQ 操作耗时 |
| `netfly_php_memory_usage_bytes` | gauge | PHP 当前内存 |
| `netfly_php_memory_peak_bytes` | gauge | PHP 峰值内存 |
| `netfly_swoole_coroutine_num` | gauge | Swoole 协程数 |

## Hyperf 接入

安装包后，Hyperf 会通过 `extra.hyperf.config` 自动加载 `Netfly\Observability\ConfigProvider`。

发布配置：

```bash
php bin/hyperf.php vendor:publish netfly/observability
```

注册中间件：

```php
return [
    'http' => [
        \Netfly\Observability\Middleware\TraceMiddleware::class,
    ],
];
```

注册指标路由：

```php
Router::get('/metrics', [\Netfly\Observability\Controller\MetricsController::class, 'text']);
```

MySQL、Redis、RabbitMQ 目前不提供自动切面埋点，需要在业务代码、适配层或监听器中显式调用对应 Collector：

```php
use Netfly\Observability\Collector\DependencyResult;
use Netfly\Observability\Collector\MysqlCollector;

$collector->record(new DependencyResult(
    component: 'mysql',
    operation: 'select',
    labels: [
        'connection' => 'default',
        'database' => 'shop',
    ],
    durationSeconds: 0.045,
    result: 'success',
    errorClass: null,
    context: [
        'scenario' => 'order_query',
        'route' => '/orders/{id}',
        'table' => 'orders',
    ],
));
```

## 配置

配置模板位于 `config/observability.php`，发布后通常在业务项目的 `config/autoload/observability.php` 中维护。

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `NETFLY_OBSERVABILITY_ENABLED` | `true` | 总开关 |
| `NETFLY_OBSERVABILITY_PROJECT` | `default` | 项目标识 |
| `APP_ENV` | `local` | 环境 |
| `NETFLY_OBSERVABILITY_SERVICE` | `hyperf` | 服务名 |
| `NETFLY_OBSERVABILITY_METRICS_ENABLED` | `true` | 指标开关 |
| `NETFLY_OBSERVABILITY_METRICS_PATH` | `/metrics` | 指标路径 |
| `NETFLY_OBSERVABILITY_LOGGING_ENABLED` | `true` | 本地文件日志开关 |
| `NETFLY_OBSERVABILITY_LOG_PATH` | `runtime/logs/netfly-observability.log` | JSON 日志路径 |
| `NETFLY_OBSERVABILITY_REMOTE_LOG_ENABLED` | `false` | 远程日志开关 |
| `NETFLY_OBSERVABILITY_REMOTE_LOG_DRIVER` | `tcp` | `tcp` 或 `http` |
| `NETFLY_OBSERVABILITY_REMOTE_LOG_HOST` | `127.0.0.1` | TCP 目标 IP 或主机名 |
| `NETFLY_OBSERVABILITY_REMOTE_LOG_PORT` | `9000` | TCP 目标端口 |
| `NETFLY_OBSERVABILITY_REMOTE_LOG_URL` | `http://127.0.0.1:9000/logs` | HTTP 目标地址 |
| `NETFLY_OBSERVABILITY_REMOTE_LOG_TIMEOUT_MS` | `50` | 远程发送超时 |
| `NETFLY_OBSERVABILITY_HTTP_ENABLED` | `true` | HTTP 采集开关 |
| `NETFLY_OBSERVABILITY_MYSQL_ENABLED` | `true` | MySQL 采集开关 |
| `NETFLY_OBSERVABILITY_REDIS_ENABLED` | `true` | Redis 采集开关 |
| `NETFLY_OBSERVABILITY_RABBITMQ_ENABLED` | `true` | RabbitMQ 采集开关 |
| `NETFLY_OBSERVABILITY_SLOW_HTTP_MS` | `1000` | HTTP 慢请求阈值 |
| `NETFLY_OBSERVABILITY_SLOW_MYSQL_MS` | `200` | MySQL 慢查询阈值 |
| `NETFLY_OBSERVABILITY_SLOW_REDIS_MS` | `100` | Redis 慢命令阈值 |
| `NETFLY_OBSERVABILITY_SLOW_RABBITMQ_MS` | `500` | RabbitMQ 慢操作阈值 |

远程 TCP 日志示例：

```php
'logging' => [
    'remote' => [
        'enabled' => true,
        'driver' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 9000,
        'timeout_ms' => 50,
    ],
],
```

远程 HTTP 日志示例：

```php
'logging' => [
    'remote' => [
        'enabled' => true,
        'driver' => 'http',
        'url' => 'http://127.0.0.1:9000/logs',
        'timeout_ms' => 50,
        'headers' => [
            'X-Token' => 'demo',
        ],
    ],
],
```

## 本地演示

启动完整演示栈：

```bash
docker compose up -d --build
```

访问地址：

| 服务 | 地址 |
|------|------|
| demo-api | http://localhost:19501 |
| Grafana | http://localhost:13000 |
| Prometheus | http://localhost:19090 |

Grafana 默认账号：

```text
admin / admin
```

demo-api 使用 Hyperf 风格目录和 Swoole HTTP 服务入口，容器内启动命令为：

```bash
php /app/demo/bin/hyperf.php start
```

演示接口：

| 接口 | 说明 |
|------|------|
| `GET /` | 查看 demo 服务信息 |
| `POST /orders` | 创建订单，真实写 MySQL、写 Redis、发布 RabbitMQ |
| `GET /orders` | 查询最近订单 |
| `GET /orders/{id}` | 查询订单缓存，命中 Redis |
| `POST /payments/callback` | 模拟支付回调，更新 MySQL 并发布 RabbitMQ |
| `POST /inventory/reserve` | 模拟库存预占，执行 Redis 扣减并更新 MySQL |
| `GET /demo/traffic` | 一次生成混合业务流量 |
| `GET /demo/slow` | 生成慢请求和慢依赖日志 |
| `GET /demo/error` | 生成受控错误日志 |
| `GET /metrics` | 输出 Prometheus 指标 |

快速生成演示数据：

```bash
curl http://localhost:19501/demo/traffic
curl http://localhost:19501/demo/slow
curl http://localhost:19501/demo/error
curl http://localhost:19501/metrics
```

查看 demo 结构化日志：

```bash
docker compose exec demo-api tail -f /app/demo/runtime/logs/netfly-observability.log
```

## Grafana 仪表盘

当前只保留新的场景化仪表盘：

| 仪表盘 | 文件 | 用途 |
|--------|------|------|
| Netfly Overview | `netfly-overview.json` | 总览请求量、错误、HTTP 延迟、依赖延迟和最新日志 |
| Netfly Request Journey | `netfly-request-journey.json` | 按 `trace_id` 查看完整请求链路 |
| Netfly MySQL | `netfly-mysql.json` | 单独查看 MySQL 查询、慢查询、错误和 SQL 日志 |
| Netfly Redis | `netfly-redis.json` | 单独查看 Redis 命令、慢命令、错误和缓存日志 |
| Netfly RabbitMQ | `netfly-rabbitmq.json` | 单独查看 RabbitMQ 发布、消费、队列和消息日志 |
| Netfly Errors & Slow | `netfly-errors-slow.json` | 集中查看错误和慢操作 |
| Netfly Raw Logs | `netfly-raw-logs.json` | Loki 原始日志检索 |

旧的 HTTP、Runtime、Logs & Trace 仪表盘已经删除。

另外新增了一组更偏图谱化的美观 dashboard，单独放在 `docker/grafana/visual-dashboards/`，并在当前 Docker Compose 中挂载到 Grafana：

| 图谱 | 文件 | 用途 |
|------|------|------|
| Netfly Visual Request Map | `netfly-visual-request-map.json` | 用请求流转图、关键指标卡、组件延迟和 trace 时间线集中查看一次请求 |
| Netfly Visual Dependency Map | `netfly-visual-dependency-map.json` | 把 MySQL、Redis、RabbitMQ 分成依赖泳道，查看调用量、P95 和事件流 |

这组图谱会出现在 Grafana 的 `Netfly Visual` 文件夹。启动或刷新方式：

```bash
docker compose up -d --build
```

如果 Grafana 已经在运行，修改图谱后可以等待 10 秒自动扫描，或手动重启 Grafana：

```bash
docker compose restart grafana
```

使用建议：

1. 先访问 `http://localhost:19501/demo/traffic` 生成真实 MySQL、Redis、RabbitMQ 流量。
2. 打开 `http://localhost:13000`，进入 `Netfly Visual` 文件夹。
3. 先看 `Netfly Visual Request Map` 找到请求、慢操作和 `trace_id`。
4. 再用同一个 `trace_id` 打开 `Netfly Visual Dependency Map`，分开查看 MySQL、Redis、RabbitMQ 调用。

## 开发验证

本机已安装依赖时：

```bash
composer validate --strict
composer test
composer analyse
```

使用 Docker 验证：

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app/demo composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```

Windows PowerShell：

```powershell
$pwdPath = (Get-Location).Path
docker run --rm -v "${pwdPath}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${pwdPath}:/app" -w /app/demo composer:2 composer validate --strict
docker run --rm -v "${pwdPath}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${pwdPath}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```

## 当前边界

- 当前包不提供自动 AOP 埋点，MySQL、Redis、RabbitMQ 依赖调用由应用侧显式调用 Collector 上报。
- 当前没有接入 Tempo、Jaeger 或 OpenTelemetry Collector，完整链路通过 Loki 日志字段查询和 Grafana dashboard 还原。
- demo 是 Hyperf 风格 Swoole 演示应用，用于尽量贴近真实项目生成 MySQL、Redis、RabbitMQ 流量，但不是完整业务系统。
- 远程日志发送是 best-effort，不阻塞主业务流程，也不会因为远程端不可用而中断请求。
