#!/bin/sh
set -e

mkdir -p /app/demo/runtime/logs
exec php /app/demo/bin/hyperf.php start
