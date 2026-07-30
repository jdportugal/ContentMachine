#!/usr/bin/env bash
# Runtime prep for the combined image, then hand off to supervisor.
set -e
cd /app

# Runtime dirs — volumes (storage / vault / database) start empty on a fresh host.
mkdir -p \
    "${VAULT_PATH:-/app/vault}" \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    database \
    bootstrap/cache

# SQLite database file lives on the `database` volume.
: "${DB_CONNECTION:=sqlite}"
if [ "${DB_CONNECTION}" = "sqlite" ]; then
    touch database/database.sqlite
fi

# A STABLE APP_KEY, persisted on the storage volume so sessions/encryption survive
# restarts and redeploys. Generated once; reused forever. An explicit APP_KEY env
# always wins.
KEYFILE=storage/app/app_key
if [ -z "${APP_KEY:-}" ]; then
    if [ -f "${KEYFILE}" ]; then
        APP_KEY="$(cat "${KEYFILE}")"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        printf '%s' "${APP_KEY}" > "${KEYFILE}"
    fi
    export APP_KEY
fi

# Migrations (queue/session/cache/app tables). Safe to re-run.
php artisan migrate --force || true
# public/storage symlink for the public disk.
php artisan storage:link 2>/dev/null || true

# exec so SIGTERM reaches supervisor (clean shutdown of every child).
exec supervisord -c /etc/supervisor/conf.d/app.conf
