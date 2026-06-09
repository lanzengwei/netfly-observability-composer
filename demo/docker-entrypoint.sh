#!/bin/sh
set -e

mkdir -p /app/demo/runtime/logs
php /app/demo/bin/generate.php &
php -S 0.0.0.0:9501 -t /app/demo/public /app/demo/public/index.php
