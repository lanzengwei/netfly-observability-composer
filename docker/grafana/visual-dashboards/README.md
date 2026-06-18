# Netfly Visual Dashboards

This directory contains the more visual Grafana dashboards for request journey and dependency topology.

## Dashboards

| Dashboard | File | Purpose |
|-----------|------|---------|
| Netfly Visual Request Map | `netfly-visual-request-map.json` | Shows request flow, scenario health, dependency latency, and trace timeline on one screen. |
| Netfly Visual Dependency Map | `netfly-visual-dependency-map.json` | Shows MySQL, Redis, and RabbitMQ as separate dependency lanes with rate, latency, and logs. |

## How it is loaded

`docker/grafana/provisioning/dashboards/dashboards.yml` registers this directory as a separate Grafana provider named `Netfly Visual Maps`.

The Docker Compose service mounts it here:

```text
./docker/grafana/visual-dashboards:/var/lib/grafana/visual-dashboards:ro
```

After starting the stack, open Grafana and find the dashboards under the `Netfly Visual` folder:

```text
http://localhost:13000
```

Default login:

```text
admin / admin
```

Grafana checks the directory every 10 seconds. If the dashboard does not appear after editing files locally, restart Grafana:

```bash
docker compose restart grafana
```
