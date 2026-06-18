FROM composer:2 AS vendor

WORKDIR /app
COPY . /app
WORKDIR /app/demo
RUN composer install --no-interaction --ignore-platform-req=ext-swoole

FROM php:8.1-cli

RUN printf "no\nno\nno\nno\nno\nno\nno\nno\nno\nno\n" | pecl install swoole-5.1.7 \
    && docker-php-ext-enable swoole \
    && docker-php-ext-install pdo_mysql sockets

WORKDIR /app/demo
COPY --from=vendor /app /app
RUN sed -i 's/\r$//' /app/demo/docker-entrypoint.sh \
    && chmod +x /app/demo/docker-entrypoint.sh

ENV NETFLY_OBSERVABILITY_ENABLED=true \
    NETFLY_OBSERVABILITY_PROJECT=demo-shop \
    APP_ENV=local \
    NETFLY_OBSERVABILITY_SERVICE=demo-api \
    NETFLY_OBSERVABILITY_LOG_PATH=/app/demo/runtime/logs/netfly-observability.log

EXPOSE 9501
CMD ["/app/demo/docker-entrypoint.sh"]
