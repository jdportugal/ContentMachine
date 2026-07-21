#!/usr/bin/env bash
set -e

# Garante as pastas de runtime (podem faltar após .dockerignore / volumes vazios).
mkdir -p \
    "${VAULT_PATH:-/var/www/html/vault}" \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache "${VAULT_PATH:-/var/www/html/vault}" 2>/dev/null || true

# Chave da aplicação (só se estiver vazia).
if [ -z "${APP_KEY}" ] && ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    php artisan key:generate --force || true
fi

# Esperar pela base de dados.
if [ "${DB_CONNECTION}" = "pgsql" ]; then
    echo "A aguardar pela base de dados em ${DB_HOST}:${DB_PORT}..."
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);" 2>/dev/null; do
        sleep 2
    done
fi

# Só o serviço principal (php-fpm) corre migrações e caches.
if [ "${1}" = "php-fpm" ]; then
    php artisan migrate --force || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
