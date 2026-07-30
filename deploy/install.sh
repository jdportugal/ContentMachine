#!/usr/bin/env bash
# ContentMachine — one-line installer.
#
#   curl -fsSL https://raw.githubusercontent.com/<you>/ContentMachine-deploy/main/install.sh | bash
#
# Self-contained: writes its own compose + Caddyfile and pulls the PUBLIC GHCR
# image, so it needs nothing from the (private) source repo. Runs the app behind
# Caddy with a free HTTPS URL via sslip.io.
set -euo pipefail

# ── Config (override via env) ────────────────────────────────────────────────
IMAGE="${IMAGE:-ghcr.io/jdportugal/contentmachine:latest}"
APP_PORT="${APP_PORT:-8080}"          # the port the image serves on
DIR="${DIR:-$HOME/contentmachine}"

# ── Docker (install if missing) ──────────────────────────────────────────────
if ! command -v docker >/dev/null 2>&1; then
  echo "→ installing Docker…"
  curl -fsSL https://get.docker.com | sh
fi

# ── Public IP → free HTTPS domain ────────────────────────────────────────────
IP="$(curl -fsSL https://api.ipify.org || hostname -I | awk '{print $1}')"
DOMAIN="${DOMAIN:-${IP}.sslip.io}"    # <ip>.sslip.io resolves to <ip>, Caddy gets a cert
echo "→ deploying at https://${DOMAIN}"

mkdir -p "${DIR}"
cd "${DIR}"

# Keep any existing API keys the operator added.
[ -f .env ] || cat > .env <<'EOF'
# Add your keys to switch from the offline 'fake' driver to real generation:
# CLIPS_DRIVER=api
# OPENAI_API_KEY=
# ELEVENLABS_API_KEY=
# ANTHROPIC_API_KEY=
# KIE_API_KEY=
EOF

cat > docker-compose.yml <<EOF
services:
  app:
    image: ${IMAGE}
    restart: unless-stopped
    environment:
      APP_URL: https://${DOMAIN}
      ASSET_URL: https://${DOMAIN}
    env_file:
      - .env
    volumes:
      - storage:/app/storage
      - vault:/app/vault
      - db:/app/database
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
  caddy_data:
  caddy_config:
EOF

cat > Caddyfile <<EOF
${DOMAIN} {
    reverse_proxy app:${APP_PORT}
}
EOF

echo "→ pulling image + starting…"
docker compose pull
docker compose up -d

cat <<EOF

✓ ContentMachine is up:  https://${DOMAIN}
  (first request may take a few seconds while Caddy fetches the certificate)

  logs:     cd ${DIR} && docker compose logs -f
  update:   cd ${DIR} && docker compose pull && docker compose up -d
  add keys: edit ${DIR}/.env then 'docker compose up -d'
EOF
