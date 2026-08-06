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

# The dashboard password (HTTP Basic over the whole app — RequireDashboardAuth).
# Persisted next to APP_KEY so it survives restarts/redeploys. Generated if the
# operator did not set one, so a deploy is NEVER reachable without a password.
# NEVER printed: container logs get shipped, shared and pasted around. The boot
# message below points at the file instead, so retrieving it is a deliberate act.
PASSFILE=storage/app/app_password
if [ -z "${APP_PASSWORD:-}" ]; then
    if [ -f "${PASSFILE}" ]; then
        APP_PASSWORD="$(cat "${PASSFILE}")"
    else
        APP_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -d '/+=' | cut -c1-24)"
    fi
fi
# ALWAYS write the file, including when the operator supplied APP_PASSWORD:
# `php artisan serve` (the web process below) only forwards an allowlist of
# variables to the server it spawns, and APP_PASSWORD is not on it. The file is
# how the app actually reads the password — skip it and the gate sees none and
# refuses every request.
printf '%s' "${APP_PASSWORD}" > "${PASSFILE}"
chmod 600 "${PASSFILE}" 2>/dev/null || true
export APP_PASSWORD
echo "[content-machine] dashboard is password-protected (HTTP Basic, any username)."
echo "[content-machine] read the password:  docker compose exec app cat ${PASSFILE}"
echo "[content-machine] or set your own:    APP_PASSWORD in the compose environment."

# One-time self-heal: builds before the resolve-time driver fix could run the
# FAKE renderer in production, leaving tiny "FAKE-*" stubs (FAKE-VIDEO, FAKE-FINAL,
# FAKE-AUDIO…) cached on the storage volume. is_file() treats them as valid
# renders, so they'd never regenerate. Purge them so real renders take over.
# Content-matched + size-capped, so real media (always far larger) is never touched.
find storage/app -type f -size -4k 2>/dev/null | while IFS= read -r f; do
    case "$(head -c 5 "$f" 2>/dev/null)" in FAKE-) rm -f "$f" ;; esac
done

# Migrations (queue/session/cache/app tables). Safe to re-run.
php artisan migrate --force || true
# public/storage symlink for the public disk.
php artisan storage:link 2>/dev/null || true

# exec so SIGTERM reaches supervisor (clean shutdown of every child).
exec supervisord -c /etc/supervisor/conf.d/app.conf
