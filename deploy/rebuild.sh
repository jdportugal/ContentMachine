#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Build and run the CURRENT production source, on the host, without GHCR.
#
# The normal path is: push to `production` → GitHub Actions builds → the host's
# update timer pulls the new image. When Actions does not fire (a push that
# creates no workflow run, an outage, a disabled runner), the registry keeps
# serving the last image it managed to build and the host has no way forward.
# This script removes that dependency: it builds the image here, from source.
#
#   curl -fsSL https://raw.githubusercontent.com/jdportugal/ContentMachine/production/deploy/rebuild.sh | bash
#
# Afterwards the host runs the locally built image and STOPS auto-updating from
# GHCR (the timer would otherwise pull the stale registry image back within five
# minutes). Run with --restore once Actions works again to hand control back.
#
# Volumes (storage, vault, database, media) are untouched: your content, keys
# and accounts survive.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

DIR="${DIR:-/opt/brand-machine}"
SRC="${SRC:-${DIR}/src}"
REPO="${REPO:-https://github.com/jdportugal/ContentMachine.git}"
BRANCH="${BRANCH:-production}"
TAG="${TAG:-contentmachine:source}"
OVERRIDE="${DIR}/docker-compose.override.yml"

log() { echo "[rebuild] $*"; }

# ── --restore: back to the published image + auto-updates ────────────────────
if [ "${1:-}" = "--restore" ]; then
    log "restoring the GHCR image and the auto-update timer"
    rm -f "${OVERRIDE}"
    systemctl enable --now brand-machine-update.timer 2>/dev/null || true
    cd "${DIR}"
    docker compose pull && docker compose up -d
    log "done — the host follows ghcr.io again"
    exit 0
fi

[ -d "${DIR}" ] || { echo "[rebuild] ${DIR} not found — is this the deploy host?" >&2; exit 1; }

# The 5-minute timer runs `docker compose pull && docker compose up -d`, which
# would replace what we build here with the stale registry image. Stop it first.
log "stopping the auto-update timer (it would pull the stale image back)"
systemctl disable --now brand-machine-update.timer 2>/dev/null || true

# ── source ───────────────────────────────────────────────────────────────────
if [ -d "${SRC}/.git" ]; then
    log "updating ${SRC}"
    git -C "${SRC}" fetch --depth 1 origin "${BRANCH}"
    git -C "${SRC}" reset --hard "origin/${BRANCH}"
else
    log "cloning ${BRANCH} into ${SRC}"
    rm -rf "${SRC}"
    git clone --depth 1 --branch "${BRANCH}" "${REPO}" "${SRC}"
fi

SHA="$(git -C "${SRC}" rev-parse --short HEAD)"
log "building ${TAG} from ${SHA} — several minutes, and it needs ~2 GB of RAM"
log "  (if the build is OOM-killed, add swap: fallocate -l 2G /swap && chmod 600 /swap && mkswap /swap && swapon /swap)"

docker build --build-arg "APP_VERSION=${SHA}" -t "${TAG}" "${SRC}"

# ── pin compose to the local image ───────────────────────────────────────────
# `pull_policy: never` keeps any stray `docker compose pull` from reaching for a
# registry copy of a tag that only exists on this host.
log "pinning ${DIR} to ${TAG}"
cat > "${OVERRIDE}" <<YAML
# Written by deploy/rebuild.sh — the app runs an image built ON THIS HOST from
# source, because the registry build did not run. Delete this file (or run
# rebuild.sh --restore) to follow ghcr.io again.
services:
  app:
    image: ${TAG}
    pull_policy: never
YAML

cd "${DIR}"
docker compose up -d --force-recreate app

log "running ${SHA}. Verify with:"
log "  docker compose exec app grep -c registration_open config/contentmachine.php   # expect 1"
