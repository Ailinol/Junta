#!/bin/sh
set -eu
umask 0022

if [ "${APP_ENV:-}" != production ] || [ "${APP_DEBUG:-false}" != false ]; then
    echo 'Junta requires APP_ENV=production and APP_DEBUG=false.' >&2
    exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
    echo 'Set a persistent APP_KEY before starting Junta. No key was generated.' >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" != mysql ]; then
    echo 'Junta production requires MySQL.' >&2
    exit 1
fi

php -r '
$key = getenv("APP_KEY");
$decoded = str_starts_with($key, "base64:") ? base64_decode(substr($key, 7), true) : $key;
$url = parse_url(getenv("APP_URL") ?: "");
if (!is_string($decoded) || strlen($decoded) !== 32 || !isset($url["host"]) || !in_array($url["scheme"] ?? "", ["http", "https"], true)) {
    fwrite(STDERR, "Configure a valid AES-256 APP_KEY and an absolute APP_URL.\n");
    exit(1);
}
'

mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ ! -w storage/app ] || [ ! -w bootstrap/cache ]; then
    echo 'storage/app and bootstrap/cache must be writable by www-data (UID 33).' >&2
    exit 1
fi

php artisan optimize --no-interaction

exec "$@"
