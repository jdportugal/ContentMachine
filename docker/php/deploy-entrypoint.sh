#!/usr/bin/env bash
# Arranque da imagem autossuficiente (single-container) usada no GHCR/deploy.
# Sem .env e sem serviços externos: SQLite + drivers de ficheiro/BD, tudo em /app.
set -e
cd /app

# Pastas de runtime — os volumes (storage, vault, database) chegam vazios no
# primeiro arranque.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database \
    "${VAULT_PATH:-/app/vault}"
touch "${DB_DATABASE:-/app/database/database.sqlite}"

# APP_KEY persistente: gerada uma vez e guardada no volume 'storage', para se
# manter estável entre recriações do contentor (updates via Watchtower). Sem
# isto, sessões e dados encriptados quebrariam a cada atualização.
KEY_FILE=storage/app_key
if [ -z "${APP_KEY:-}" ]; then
    if [ -s "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        printf '%s' "$APP_KEY" > "$KEY_FILE"
    fi
    export APP_KEY
fi

# Migrações + caches (config:cache apanha o APP_KEY exportado acima).
php artisan migrate --force || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Devolver a posse ao www-data DEPOIS dos comandos artisan (corridos como root),
# para o php-fpm e o worker poderem escrever em runtime.
chown -R www-data:www-data storage bootstrap/cache database "${VAULT_PATH:-/app/vault}" 2>/dev/null || true

exec "$@"
