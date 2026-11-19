# syntax=docker/dockerfile:1
FROM php:8.5-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl libfcgi-bin libicu-dev libzip-dev unzip \
    && docker-php-ext-install -j2 bcmath intl pcntl pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=build_ca \
    if [ -s /run/secrets/build_ca ]; then export COMPOSER_CAFILE=/run/secrets/build_ca; fi; \
    composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && APP_ENV=production OPENAI_ENABLED=false php artisan package:discover --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim AS node

FROM vendor AS frontend
COPY --from=node /usr/local/ /usr/local/
ENV APP_ENV=production APP_DEBUG=false OPENAI_ENABLED=false INERTIA_SSR_ENABLED=false
ENV APP_URL=http://build.invalid VITE_APP_NAME=Junta
RUN --mount=type=secret,id=build_ca \
    if [ -s /run/secrets/build_ca ]; then export NODE_EXTRA_CA_CERTS=/run/secrets/build_ca; fi; \
    npm ci --no-audit --no-fund \
    && npm run build \
    && test -s public/build/manifest.json \
    && rm -f public/hot

FROM php-base AS app
COPY --from=vendor /var/www/html /var/www/html
COPY --from=frontend /var/www/html/public/build /var/www/html/public/build
COPY php.production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY php-fpm.production.conf /usr/local/etc/php-fpm.d/zz-production.conf
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/junta-entrypoint
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u=rwX,go=rX storage bootstrap/cache
USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
ENTRYPOINT ["junta-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS web
COPY public /var/www/html/public
COPY --from=frontend /var/www/html/public/build /var/www/html/public/build
COPY nginx.production.conf /etc/nginx/templates/default.conf.template
RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
ENV NGINX_PORT=8080 PHP_FPM_UPSTREAM=app:9000 APP_PUBLIC_PATH=/var/www/html/public
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1

FROM axllent/mailpit:v1.34.0 AS mail-capture

FROM app AS railway
USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends gettext-base nginx-light supervisor util-linux \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default \
    && sed -i 's/worker_processes auto;/worker_processes 1;/' /etc/nginx/nginx.conf \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log
COPY --from=mail-capture /mailpit /usr/local/bin/mailpit
COPY nginx.production.conf /etc/nginx/junta.conf.template
COPY php-fpm.railway.conf /usr/local/etc/php-fpm.d/zz-railway.conf
COPY supervisord.railway.conf /etc/supervisor/junta.conf
COPY --chmod=755 railway-entrypoint.sh /usr/local/bin/junta-railway-entrypoint
ENV PORT=8080 MAIL_CAPTURE_ENABLED=false
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl --fail --silent --output /dev/null "http://127.0.0.1:${PORT}/up" || exit 1
ENTRYPOINT ["junta-railway-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/junta.conf"]
