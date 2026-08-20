#!/usr/bin/env bash
# Brand Machine — one-shot installer for a fresh Ubuntu host.
#
# EASIEST: paste this whole file into DigitalOcean → Create Droplet → Advanced
# Options → "Add Initialization scripts (user data)". It self-deploys on first
# boot; no SSH. Or run it over SSH:  bash install.sh
#
# Self-contained: installs Docker, writes its own compose + Caddyfile, pulls the
# PUBLIC image, and serves behind Caddy with a free HTTPS URL via <ip>.sslip.io.
set -euo pipefail

IMAGE="${IMAGE:-ghcr.io/jdportugal/contentmachine:latest}"   # public GHCR image
APP_PORT="${APP_PORT:-8080}"                                 # port the image serves on
DIR="${DIR:-/opt/brand-machine}"                             # where compose lives
# Output ceiling for the clip/SFX LLM calls. Written into the compose file rather
# than left to the config default so it is VISIBLE and editable on the box: an SFX
# response is a whole TSX component wrapped in JSON, and a ceiling that is too low
# truncates it mid-file. Raise this if generation reports a truncated response.
CLIPS_MAX_TOKENS="${CLIPS_MAX_TOKENS:-16000}"
# Seconds ONE model call may take. Writing a component is a minutes-long response;
# a ceiling below it times out and retries the whole generation from scratch.
CLIPS_LLM_TIMEOUT="${CLIPS_LLM_TIMEOUT:-600}"

log()   { echo "[brand-machine] $*"; }
retry() { local n=0; until "$@"; do n=$((n+1)); [ "$n" -ge 30 ] && return 1; sleep 5; done; }

# curl is used below — present on Ubuntu, but be safe on minimal images.
command -v curl >/dev/null 2>&1 || { apt-get update -y && apt-get install -y curl; }

# ── Docker (install + start if missing) ──────────────────────────────────────
if ! command -v docker >/dev/null 2>&1; then
  log "installing Docker…"
  retry curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
  sh /tmp/get-docker.sh
fi
systemctl enable --now docker 2>/dev/null || true
log "waiting for the Docker daemon…"
retry docker info >/dev/null 2>&1

# ── Public IP → free HTTPS domain ────────────────────────────────────────────
IP=""
for _ in $(seq 1 30); do
  IP="$(curl -fsSL https://api.ipify.org 2>/dev/null || true)"; [ -n "$IP" ] && break
  IP="$(hostname -I 2>/dev/null | awk '{print $1}')";          [ -n "$IP" ] && break
  sleep 3
done
DOMAIN="${DOMAIN:-${IP}.sslip.io}"    # <ip>.sslip.io resolves to <ip>; Caddy gets a cert
log "deploying at https://${DOMAIN}"

mkdir -p "${DIR}"; cd "${DIR}"

# No .env — all config is either automatic here or set in the app's Settings page
# (API keys, channels, models…). Drop a stale one if present.
rm -f .env 2>/dev/null || true

# The nightly collection (monitoring:collect) runs at 00:00 in the app's timezone.
# Adopt the host's so "midnight" is the operator's midnight, not UTC. Falls back
# to UTC on hosts without timedatectl or /etc/timezone.
TZ_HOST="${APP_TIMEZONE:-}"
if [ -z "$TZ_HOST" ]; then
  TZ_HOST="$(timedatectl show -p Timezone --value 2>/dev/null || true)"
fi
if [ -z "$TZ_HOST" ] && [ -r /etc/timezone ]; then
  TZ_HOST="$(cat /etc/timezone 2>/dev/null || true)"
fi
[ -z "$TZ_HOST" ] && TZ_HOST="UTC"
log "timezone: ${TZ_HOST} (nightly collection runs at 00:00 there)"

cat > docker-compose.yml <<EOF
services:
  app:
    image: ${IMAGE}
    restart: unless-stopped
    # Everything the container needs is here or automatic (APP_KEY is generated on
    # the storage volume; drivers auto-enable in production; keys live in Settings).
    environment:
      APP_URL: https://${DOMAIN}
      ASSET_URL: https://${DOMAIN}
      APP_TIMEZONE: ${TZ_HOST}
      CLIPS_MAX_TOKENS: "${CLIPS_MAX_TOKENS}"
      CLIPS_LLM_TIMEOUT: "${CLIPS_LLM_TIMEOUT}"
    volumes:
      - storage:/app/storage
      - vault:/app/vault
      - db:/app/database
      # Downloaded post thumbnails + AI-generated images live under public/media.
      # Persist them, or every image update wipes them from the container layer.
      - media:/app/public/media
    expose:
      - "${APP_PORT}"

  caddy:
    image: caddy:2
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - app

volumes:
  storage:
  vault:
  db:
  media:
  caddy_data:
  caddy_config:
EOF

cat > Caddyfile <<EOF
${DOMAIN} {
    reverse_proxy app:${APP_PORT}
}
EOF

log "pulling image…"
if ! docker compose pull 2>/tmp/bm-pull.err; then
  cat /tmp/bm-pull.err >&2 || true
  if grep -qiE 'unauthorized|denied|manifest unknown|not found' /tmp/bm-pull.err; then
    cat >&2 <<MSG

──────────────────────────────────────────────────────────────────────────────
✗ Can't pull ${IMAGE} — it's PRIVATE (or not built yet).

  ONE-TIME FIX — make the GHCR package public, then re-run this same command:

    1) open:  https://github.com/users/jdportugal/packages/container/contentmachine/settings
    2) Danger Zone → Change visibility → Public → confirm

  If that page 404s, the image was never built — check the build at:
    https://github.com/jdportugal/ContentMachine/actions  (workflow: "Publish image")
──────────────────────────────────────────────────────────────────────────────
MSG
    exit 1
  fi
  log "transient pull error — retrying…"; retry docker compose pull
fi
log "starting…"; docker compose up -d

# ── Auto-update: a host systemd timer pulls the latest image every 5 min and
# recreates the app only if it changed (a no-op otherwise). Host-side, Docker
# only — no in-container sidecar, network path, or shared token to go wrong.
if command -v systemctl >/dev/null 2>&1; then
  log "installing the auto-update timer…"
  cat > /etc/systemd/system/brand-machine-update.service <<UNIT
[Unit]
Description=Update Brand Machine to the latest image
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
WorkingDirectory=${DIR}
ExecStart=/bin/sh -c 'docker compose pull --quiet && docker compose up -d'
UNIT
  cat > /etc/systemd/system/brand-machine-update.timer <<UNIT
[Unit]
Description=Check for Brand Machine updates every 5 minutes

[Timer]
OnBootSec=3min
OnUnitActiveSec=5min
Persistent=true

[Install]
WantedBy=timers.target
UNIT
  systemctl daemon-reload 2>/dev/null || true
  systemctl enable --now brand-machine-update.timer 2>/dev/null || true
fi
log "done"

cat <<EOF

✓ Brand Machine is up:  https://${DOMAIN}
  (first request takes a few seconds while Caddy fetches the certificate)

  updates: automatic — a systemd timer pulls the latest every 5 min.
  logs:    cd ${DIR} && docker compose logs -f
  now:     cd ${DIR} && docker compose pull && docker compose up -d   (update instantly)
  keys:    open the app → Settings → API Keys (no .env, no SSH)
EOF
