#!/bin/sh
set -eu
umask 0022
cd /var/www/html

export PORT="${PORT:-8080}"
export MAIL_CAPTURE_ENABLED="${MAIL_CAPTURE_ENABLED:-false}"

php -r '
$port = filter_var(getenv("PORT"), FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]]);
if ($port === false || !in_array(getenv("MAIL_CAPTURE_ENABLED"), ["true", "false"], true)) {
    fwrite(STDERR, "Set PORT to a valid TCP port and MAIL_CAPTURE_ENABLED to true or false.\n");
    exit(1);
}
'

if [ -n "${RAILWAY_VOLUME_MOUNT_PATH:-}" ] && [ "$RAILWAY_VOLUME_MOUNT_PATH" != /var/www/html/storage ]; then
    echo 'Mount the Junta volume at /var/www/html/storage.' >&2
    exit 1
fi

mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown www-data:www-data storage storage/app storage/app/private storage/app/public \
    storage/framework storage/framework/cache storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ "${1:-}" != supervisord ]; then
    exec runuser -u www-data -- junta-entrypoint "$@"
fi

if [ -n "${RAILWAY_ENVIRONMENT_ID:-}" ] && [ "${RAILWAY_VOLUME_MOUNT_PATH:-}" != /var/www/html/storage ]; then
    echo 'Attach the persistent Junta volume at /var/www/html/storage before deploying.' >&2
    exit 1
fi

export NGINX_PORT="$PORT"
export PHP_FPM_UPSTREAM=127.0.0.1:9000
export APP_PUBLIC_PATH=/var/www/html/public
envsubst '${NGINX_PORT} ${PHP_FPM_UPSTREAM} ${APP_PUBLIC_PATH}' \
    < /etc/nginx/junta.conf.template > /etc/nginx/conf.d/junta.conf
nginx -t

runuser -u www-data -- junta-entrypoint true
exec "$@"
