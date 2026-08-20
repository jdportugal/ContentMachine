#!/usr/bin/env bash
# Runtime prep for the combined image, then hand off to supervisor.
set -e
cd /app

# The render ceilings, which have to hold this order or a still-running render is
# handed to a second worker and two of them write the same file:
#   CLIPS_RENDER_TIMEOUT  <  QUEUE_TIMEOUT  <  DB_QUEUE_RETRY_AFTER
# A real clip (scenes with SFX, karaoke and images over a source video) runs well
# past the old 25 minutes on a 2-vCPU host. Supervisor needs QUEUE_TIMEOUT to
# exist, so give it a default here rather than in the program line.
: "${QUEUE_TIMEOUT:=3300}"
: "${DB_QUEUE_RETRY_AFTER:=3600}"
export QUEUE_TIMEOUT DB_QUEUE_RETRY_AFTER

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

# The admin account. Seeded at boot rather than offered as a "create the first
# account" screen: this app is on a public URL, and such a screen belongs to
# whoever reaches it first. No-op once a user exists.
# The generated password is written to storage/app/admin_password — never to
# stdout, because container logs get shipped, shared and pasted around.
php artisan app:ensure-admin --no-interaction || true
if [ -f storage/app/admin_password ]; then
    echo "[content-machine] admin account ready. Read the first-login password with:"
    echo "[content-machine]   docker compose exec app cat storage/app/admin_password"
    echo "[content-machine] change it under Settings → Account, then delete that file."
fi

# exec so SIGTERM reaches supervisor (clean shutdown of every child).
exec supervisord -c /etc/supervisor/conf.d/app.conf
