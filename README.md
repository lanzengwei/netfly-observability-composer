# netfly-observability

面向 Hyperf 3.x 的可观测性 Composer 包，为 PHP 8.1+ / Swoole 5.x 应用提供 **Prometheus 指标** 与 **Loki 结构化日志** 采集能力。

本包负责在生产应用中采集并上报数据；仓库内的 `demo/` 应用则用于持续生成示例流量，配合 Docker Compose 可观测性栈进行本地演示与验证。

---

## 目录

- [功能概览](#功能概览)
- [架构说明](#架构说明)
- [安装与集成](#安装与集成)
- [配置说明](#配置说明)
- [链路追踪（Trace）](#链路追踪trace)
- [Prometheus 指标](#prometheus-指标)
- [结构化日志](#结构化日志)
- [Grafana 与 LogQL](#grafana-与-logql)
- [本地演示栈](#本地演示栈)
- [开发验证](#开发验证)
- [非目标与限制](#非目标与限制)

---

## 功能概览

### 采集范围

| 类别 | 指标 | 日志 | 说明 |
|------|------|------|------|
| PHP 运行时 | 内存占用、峰值、Swoole 协程数 | — | 通过 `RuntimeCollector` 周期性采集 |
| HTTP 请求 | 请求数、耗时直方图 | 请求日志、慢请求日志 | 由 `TraceMiddleware` 自动记录 |
| MySQL | 查询数、耗时直方图 | 查询日志、慢查询日志 | 通过 Collector + AOP 接入 |
| Redis | 命令数、耗时直方图 | 命令日志、慢命令日志 | 通过 Collector + AOP 接入 |
| RabbitMQ | 发布/消费数、耗时直方图 | AMQP 日志、慢操作日志 | 通过 Collector + AOP 接入 |
| 应用错误 | — | 错误日志 | 业务代码或框架异常时写入 |
| 慢操作 | — | 慢日志 | 超过阈值时额外写入 `log_type=slow` |

### 统一身份标识

所有指标与日志均携带以下低基数标签/字段，便于在 Prometheus 与 Loki 中按项目、环境、服务过滤：

- `project`：项目或租户标识
- `env`：运行环境（如 `local`、`dev`、`staging`、`prod`）
- `service`：项目内的服务名称

### 核心特性

- **开关粒度细**：总开关、指标开关、日志开关、各 Collector 开关均可独立控制
- **慢操作检测**：HTTP、MySQL、Redis、RabbitMQ 均可配置慢操作阈值（毫秒）
- **敏感信息脱敏**：日志 `context` 中匹配 `password`、`token`、`secret`、`authorization` 的字段自动替换为 `[redacted]`
- **长字符串截断**：单字段超过 2048 字符时自动截断
- **轻量链路关联**：通过 `trace_id` 将同一请求下的 HTTP、依赖调用、错误、慢日志串联查询，无需完整分布式追踪后端

---

## 架构说明

### 数据流

```text
Hyperf 应用（安装 netfly/observability）
    │
    ├─► /metrics 端点 ──► Prometheus 定时抓取 ──► Grafana 仪表盘
    │
    └─► JSON 日志文件 ──► Promtail 采集推送 ──► Loki 存储 ──► Grafana Explore / 日志面板
```

### 包内组件

```text
src/
├── ConfigProvider.php          # Hyperf 自动注册入口
├── Middleware/TraceMiddleware  # 生成/透传 trace_id，记录 HTTP 指标与日志
├── Context/TraceContext        # 协程上下文存储 trace_id
├── Trace/TraceIdGenerator      # trace_id 生成与校验
├── Collector/                  # 各维度采集器
│   ├── HttpCollector
│   ├── MysqlCollector / RedisCollector / RabbitMqCollector
│   └── RuntimeCollector
├── Metrics/                    # 指标注册与 Prometheus 文本渲染
├── Logging/                    # JSON 格式化、脱敏、文件写入
├── Controller/MetricsController # /metrics 响应
└── Aspect/                     # MySQL / Redis / RabbitMQ AOP 标记类
```

### Hyperf 集成方式

包通过 `ConfigProvider` 向 Hyperf 容器注册依赖。应用侧需要：

1. 安装 Composer 包并发布配置
2. 将 `TraceMiddleware` 加入 HTTP 中间件链（通常放在靠前位置）
3. 注册 `/metrics` 路由，指向 `MetricsController::text()`
4. 在 MySQL / Redis / RabbitMQ 调用处通过 AOP 或手动调用对应 Collector 的 `record()` 方法上报依赖数据

> 当前仓库中 `Aspect/*` 类为 AOP 注册标记，具体切面逻辑由应用在启用数据库/缓存/消息队列埋点时配置目标类。

---

## 安装与集成

### 1. 安装 Composer 包

```bash
composer require netfly/observability
```

**运行要求：**

- PHP ^8.1
- Hyperf 3.x
- Swoole 5.x（协程上下文、`TraceContext` 依赖）
- 扩展：`ext-json`

### 2. 发布配置文件

在 Hyperf 项目中执行：

```bash
php bin/hyperf.php vendor:publish netfly/observability
```

配置文件将发布到 `config/autoload/observability.php`。

### 3. 注册 HTTP 中间件

在 `config/autoload/middlewares.php` 的 `http` 数组中加入：

```php
\Netfly\Observability\Middleware\TraceMiddleware::class,
```

建议放在认证中间件之前，确保每个请求都能获得 `trace_id` 并完成 HTTP 采集。

### 4. 注册 Metrics 路由

在路由文件中添加（路径可通过配置修改，默认 `/metrics`）：

```php
Router::get('/metrics', [\Netfly\Observability\Controller\MetricsController::class, 'text']);
```

### 5. 依赖采集（可选）

对于 MySQL、Redis、RabbitMQ，在 AOP 切面或业务监听器中构造 `DependencyResult` 并调用对应 Collector：

```php
use Netfly\Observability\Collector\DependencyResult;
use Netfly\Observability\Collector\MysqlCollector;

// 示例：记录一次 MySQL 查询
$collector->record(new DependencyResult(
    component: 'mysql',
    operation: 'select',
    labels: ['connection' => 'default', 'database' => 'shop'],
    durationSeconds: 0.045,
    result: 'success',
    errorClass: null,
    context: ['table' => 'orders']
));
```

### 6. 运行时指标（可选）

在定时任务或请求结束前调用：

```php
$runtimeCollector->collect();
```

---

## 配置说明

配置文件路径：`config/autoload/observability.php`

### 配置项结构

```php
return [
    'enabled' => true,           // 总开关：关闭后所有采集与可观测性日志均停止
    'project' => 'default',      // 项目标识
    'env' => 'local',            // 环境名称
    'service' => 'hyperf',       // 服务名称
    'metrics' => [
        'enabled' => true,       // 指标开关
        'path' => '/metrics',    // Metrics 端点路径（路由需自行注册）
    ],
    'logging' => [
        'enabled' => true,       // 可观测性 JSON 日志开关
        'path' => BASE_PATH . '/runtime/logs/netfly-observability.log',
    ],
    'http' => ['enabled' => true],
    'mysql' => ['enabled' => true],
    'redis' => ['enabled' => true],
    'rabbitmq' => ['enabled' => true],
    'slow_threshold_ms' => [
        'http' => 1000,          // HTTP 慢请求阈值（毫秒），0 表示禁用慢日志
        'mysql' => 200,
        'redis' => 100,
        'rabbitmq' => 500,
    ],
];
```

### 环境变量对照表

| 环境变量 | 对应配置 | 默认值 | 说明 |
|----------|----------|--------|------|
| `NETFLY_OBSERVABILITY_ENABLED` | `enabled` | `true` | 总开关 |
| `NETFLY_OBSERVABILITY_PROJECT` | `project` | `default` | 项目标识 |
| `APP_ENV` | `env` | `local` | 环境名称 |
| `NETFLY_OBSERVABILITY_SERVICE` | `service` | `hyperf` | 服务名称 |
| `NETFLY_OBSERVABILITY_METRICS_ENABLED` | `metrics.enabled` | `true` | 指标开关 |
| `NETFLY_OBSERVABILITY_METRICS_PATH` | `metrics.path` | `/metrics` | Metrics 路径 |
| `NETFLY_OBSERVABILITY_LOGGING_ENABLED` | `logging.enabled` | `true` | 日志开关 |
| `NETFLY_OBSERVABILITY_LOG_PATH` | `logging.path` | `runtime/logs/netfly-observability.log` | 日志文件路径 |
| `NETFLY_OBSERVABILITY_HTTP_ENABLED` | `http.enabled` | `true` | HTTP 采集开关 |
| `NETFLY_OBSERVABILITY_MYSQL_ENABLED` | `mysql.enabled` | `true` | MySQL 采集开关 |
| `NETFLY_OBSERVABILITY_REDIS_ENABLED` | `redis.enabled` | `true` | Redis 采集开关 |
| `NETFLY_OBSERVABILITY_RABBITMQ_ENABLED` | `rabbitmq.enabled` | `true` | RabbitMQ 采集开关 |
| `NETFLY_OBSERVABILITY_SLOW_HTTP_MS` | `slow_threshold_ms.http` | `1000` | HTTP 慢阈值 |
| `NETFLY_OBSERVABILITY_SLOW_MYSQL_MS` | `slow_threshold_ms.mysql` | `200` | MySQL 慢阈值 |
| `NETFLY_OBSERVABILITY_SLOW_REDIS_MS` | `slow_threshold_ms.redis` | `100` | Redis 慢阈值 |
| `NETFLY_OBSERVABILITY_SLOW_RABBITMQ_MS` | `slow_threshold_ms.rabbitmq` | `500` | RabbitMQ 慢阈值 |

### 开关行为说明

| 开关组合 | 行为 |
|----------|------|
| `enabled=false` | 禁用全部采集；中间件不再写入 HTTP 指标/日志 |
| `metrics.enabled=false` | 不注册指标样本；`/metrics` 不输出本包指标 |
| `logging.enabled=false` | 不写入可观测性 JSON 日志文件 |
| `http/mysql/redis/rabbitmq.enabled=false` | 仅禁用对应 Collector，其他维度不受影响 |

---

## 链路追踪（Trace）

本包实现**轻量级链路关联**，非完整 OpenTelemetry 分布式追踪。

### trace_id 规则

1. **优先透传**：若请求头携带 `X-Trace-Id` 且格式合法（8–128 位字母数字及 `._-`），则沿用该值
2. **自动生成**：请求头缺失或非法时，生成 32 位十六进制随机 ID（`bin2hex(random_bytes(16))`）
3. **协程存储**：通过 `TraceContext` 存入 Hyperf 协程上下文，请求结束后清除
4. **响应回写**：HTTP 响应头附带 `X-Trace-Id`，便于调用方关联
5. **日志贯穿**：同一请求周期内产生的 request、error、slow、db、redis、amqp、app 日志均携带相同 `trace_id`

### span 字段（可选）

日志 JSON 可包含 `span_id`、`parent_span_id`，用于在单请求内对依赖调用链排序。这些字段存放在 JSON 正文中，**不作为 Loki 标签**。

---

## Prometheus 指标

所有指标使用 `netfly_` 前缀。每个样本自动附加公共标签：`project`、`env`、`service`。

### 指标清单

#### HTTP

| 指标名 | 类型 | 额外标签 | 说明 |
|--------|------|----------|------|
| `netfly_http_requests_total` | Counter | `method`, `route`, `status` | HTTP 请求总数 |
| `netfly_http_request_duration_seconds_bucket` | Histogram | `method`, `route`, `status`, `le` | 请求耗时分布 |
| `netfly_http_request_duration_seconds_count` | Counter | `method`, `route`, `status` | 直方图计数 |
| `netfly_http_request_duration_seconds_sum` | Gauge | `method`, `route`, `status` | 耗时累计 |

默认直方图桶（秒）：`0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10`

#### MySQL

| 指标名 | 类型 | 额外标签 | 说明 |
|--------|------|----------|------|
| `netfly_db_queries_total` | Counter | `connection`, `database`, `operation`, `result` | 查询总数 |
| `netfly_db_query_duration_seconds_bucket` | Histogram | 同上 + `le` | 查询耗时分布 |

#### Redis

| 指标名 | 类型 | 额外标签 | 说明 |
|--------|------|----------|------|
| `netfly_redis_commands_total` | Counter | `pool`, `command`, `result` | 命令总数 |
| `netfly_redis_command_duration_seconds_bucket` | Histogram | 同上 + `le` | 命令耗时分布 |

#### RabbitMQ

| 指标名 | 类型 | 额外标签 | 说明 |
|--------|------|----------|------|
| `netfly_amqp_publish_total` | Counter | `exchange`, `queue`, `routing_key`, `operation`, `result` | 发布总数 |
| `netfly_amqp_consume_total` | Counter | 同上 | 消费总数 |
| `netfly_amqp_duration_seconds_bucket` | Histogram | 同上 + `le` | AMQP 操作耗时分布 |

#### PHP / Swoole 运行时

| 指标名 | 类型 | 说明 |
|--------|------|------|
| `netfly_php_memory_usage_bytes` | Gauge | 当前内存占用（`memory_get_usage(true)`） |
| `netfly_php_memory_peak_bytes` | Gauge | 峰值内存（`memory_get_peak_usage(true)`） |
| `netfly_swoole_coroutine_num` | Gauge | 当前协程数量（非 Swoole 环境为 0） |

### 高基数防护

以下值**不会**作为 Prometheus 标签，避免指标爆炸：

- 原始 SQL 语句
- Redis Key
- 消息 Payload
- 异常消息全文
- `trace_id`

这些信息仅出现在 JSON 日志的 `context` 字段中，且经过脱敏与截断处理。

---

## 结构化日志

### 输出格式

每行一条 JSON（JSON Lines），UTC 时间戳，写入 `logging.path` 指定文件。

### 公共字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `timestamp` | string | RFC3339 扩展格式 UTC 时间 |
| `project` | string | 项目标识 |
| `env` | string | 环境名称 |
| `service` | string | 服务名称 |
| `level` | string | 日志级别：`info`、`warning`、`error` 等 |
| `log_type` | string | 日志分类（见下表） |
| `trace_id` | string\|null | 链路 ID |
| `span_id` | string\|null | 可选 Span ID |
| `parent_span_id` | string\|null | 可选父 Span ID |
| `message` | string | 人类可读摘要 |
| `context` | object | 附加上下文（已脱敏） |

### 提升字段

以下字段若存在于 `context` 中，会提升到 JSON 顶层便于查询：

`duration_ms`、`threshold_ms`、`component`、`operation`、`route`、`status`、`exception_class`

### log_type 分类

| 值 | 触发场景 | 典型附加字段 |
|----|----------|--------------|
| `request` | HTTP 请求完成 | `method`, `route`, `status`, `duration_ms`, `uri`, `client_ip`, `user_agent` |
| `error` | 未捕获异常或业务错误 | `exception_class`, `file`, `line`, 裁剪后的堆栈 |
| `slow` | 耗时超过阈值 | `duration_ms`, `threshold_ms`, `component`, `operation` |
| `db` | MySQL 查询完成 | `connection`, `database`, `operation`, `result`, `duration_ms` |
| `redis` | Redis 命令完成 | `pool`, `command`, `result`, `duration_ms` |
| `amqp` | RabbitMQ 发布/消费完成 | `exchange`, `queue`, `routing_key`, `operation`, `result` |
| `app` | 业务自定义可观测性日志 | 由调用方传入 |

### Loki 标签策略

Promtail 从 JSON 中提取以下字段作为 **Loki 流标签**（低基数）：

- `project`
- `env`
- `service`
- `level`
- `log_type`

`trace_id` **仅存在于 JSON 正文**，不作为 Loki 标签，避免标签基数过高。查询时通过 `| json` 管道过滤。

### 日志示例

**HTTP 请求日志：**

```json
{
  "timestamp": "2026-06-10T08:15:30.123456+00:00",
  "project": "demo-shop",
  "env": "local",
  "service": "demo-api",
  "level": "info",
  "log_type": "request",
  "trace_id": "a1b2c3d4e5f6789012345678abcdef01",
  "message": "HTTP request completed",
  "method": "GET",
  "route": "/demo/orders",
  "status": 200,
  "duration_ms": 45.2,
  "context": {
    "uri": "/demo/orders",
    "client_ip": "172.18.0.1",
    "user_agent": "curl/8.0"
  }
}
```

**慢查询日志：**

```json
{
  "timestamp": "2026-06-10T08:15:30.456789+00:00",
  "project": "demo-shop",
  "env": "local",
  "service": "demo-api",
  "level": "warning",
  "log_type": "slow",
  "trace_id": "a1b2c3d4e5f6789012345678abcdef01",
  "message": "Slow dependency call",
  "component": "mysql",
  "operation": "select",
  "duration_ms": 250.5,
  "threshold_ms": 200,
  "context": { "table": "orders", "connection": "default" }
}
```

---

## Grafana 与 LogQL

演示栈启动后，Grafana 会自动配置 Prometheus 与 Loki 数据源，并加载预置仪表盘。

### 预置仪表盘

| 仪表盘 | 内容 |
|--------|------|
| **Overview** | 请求速率、延迟分位数、错误率、PHP 内存、Swoole 协程数 |
| **HTTP** | 路由级请求量、延迟、状态码分布、慢请求 |
| **MySQL** | 查询数、耗时、错误率、慢查询 |
| **Redis** | 命令数、耗时、错误率、慢命令 |
| **RabbitMQ** | 发布/消费数、耗时、错误率 |
| **Runtime** | PHP 内存与协程运行时指标 |
| **Logs & Trace** | 请求/错误/慢/依赖日志浏览与 trace_id 检索 |

### 仪表盘变量

- `$project`：项目
- `$env`：环境
- `$service`：服务
- `$log_type`：日志类型
- `$trace_id`：链路 ID
- `$slow_ms`：慢操作毫秒阈值

### 常用 LogQL 查询

**按 trace_id 查全链路日志：**

```logql
{project="$project", service="$service", log_type="request"} | json | trace_id="$trace_id"
```

**查错误日志：**

```logql
{project="$project", log_type="error"} | json
```

**查慢操作（可调阈值）：**

```logql
{project="$project", log_type="slow"} | json | duration_ms > $slow_ms
```

**查某次请求的所有依赖调用：**

```logql
{project="$project", log_type=~"db|redis|amqp"} | json | trace_id="$trace_id"
```

**统计错误率（LogQL 指标查询）：**

```logql
sum(rate({project="$project", log_type="error"}[5m])) by (service)
```

---

## 本地演示栈

仓库提供完整的 Docker Compose 环境，无需本地安装 PHP 8.1 即可体验指标与日志全链路。

### 启动

```bash
docker compose up -d --build
```

首次启动会构建 `demo-api` 镜像并初始化 MySQL 表结构。

### 服务与端口

| 服务 | 容器内端口 | 宿主机地址 | 说明 |
|------|-----------|-----------|------|
| demo-api | 9501 | http://localhost:19501 | 演示 API，暴露 `/metrics` |
| Prometheus | 9090 | http://localhost:19090 | 指标存储，每 5s 抓取 demo-api |
| Grafana | 3000 | http://localhost:13000 | 可视化（账号 `admin` / 密码 `admin`） |
| Loki | 3100 | 仅容器内 | 日志存储 |
| Promtail | — | 仅容器内 | 采集 `demo-logs` 卷中的 JSON 日志 |
| MySQL 8.4 | 3306 | 仅容器内 | 演示关系型依赖 |
| Redis 7 | 6379 | 仅容器内 | 演示缓存依赖 |
| RabbitMQ 3 | 5672 / 15672 | 仅容器内 | 演示消息队列依赖 |

### 演示 API 端点

| 路径 | 说明 |
|------|------|
| `GET /` | 触发一轮模拟流量（HTTP + MySQL + Redis + RabbitMQ），返回 JSON |
| `GET /metrics` | 输出 Prometheus 文本格式指标（访问前也会 tick 一次） |
| `GET /demo/slow` | 模拟慢 HTTP 请求（约 250ms），产生 `log_type=slow` |
| `GET /demo/error` | 模拟受控错误，产生 `log_type=error` |

### 演示数据流

1. `demo-api` 持续写入 JSON 日志到 `/app/demo/runtime/logs/netfly-observability.log`
2. 日志通过 Docker Volume `demo-logs` 挂载给 Promtail（容器内路径 `/var/log/demo/*.log`）
3. Promtail 解析 JSON，提取 `project`、`env`、`service`、`level`、`log_type` 作为 Loki 标签后推送
4. Prometheus 从 `demo-api:9501/metrics` 抓取 `netfly_*` 指标
5. Grafana 读取 Prometheus + Loki，展示预置 Netfly 仪表盘

### 验证演示栈

```bash
# 查看指标
curl http://localhost:19501/metrics | grep netfly_

# 触发一次流量
curl http://localhost:19501/

# 查看日志文件（需进入容器或查看 volume）
docker compose exec demo-api tail -f /app/demo/runtime/logs/netfly-observability.log
```

打开 Grafana（http://localhost:13000），在 **Logs & Trace** 仪表盘中输入 `trace_id` 即可查看单次请求的完整日志链。

---

## 开发验证

本地机器可能未安装 PHP 8.1，可通过 Docker 运行校验命令。

### Linux / macOS

```bash
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app/demo composer:2 composer validate --strict
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```

### Windows PowerShell

```powershell
$pwdPath = (Get-Location).Path
docker run --rm -v "${pwdPath}:/app" -w /app composer:2 composer validate --strict
docker run --rm -v "${pwdPath}:/app" -w /app/demo composer:2 composer validate --strict
docker run --rm -v "${pwdPath}:/app" -w /app php:8.1-cli vendor/bin/phpunit
docker run --rm -v "${pwdPath}:/app" -w /app php:8.1-cli vendor/bin/phpstan analyse --memory-limit=512M
docker compose config
```

### 本地有 PHP 8.1 时

```bash
composer test      # PHPUnit 单元/特性测试
composer analyse   # PHPStan 静态分析
composer check     # 依次执行 test + analyse
```

### 测试覆盖范围

- `trace_id` 生成、校验、透传与响应头回写
- JSON 日志字段完整性与格式化
- Collector 开关抑制行为
- 指标名称与标签正确性
- 慢阈值分类逻辑
- 敏感字段脱敏与长字符串截断
- `/metrics` 端点输出与禁用行为
- Docker Compose 配置合法性

---

## 非目标与限制

本版本**不包含**以下能力：

| 项目 | 说明 |
|------|------|
| OpenTelemetry Collector 管道 | 不接入 OTel SDK 或 Collector 导出 |
| 跨服务分布式追踪存储 | 仅提供单服务内的 `trace_id` 日志关联 |
| Loki 按 trace_id 打标签 | `trace_id` 始终为 JSON 字段，通过 LogQL `\| json` 查询 |
| 自动 AOP 埋点 | Aspect 类为标记，具体切面需应用侧配置 |

---

## 许可证

MIT
