# syntax=docker/dockerfile:1
# ────────────────────────────────────────────────────────────────────────────
# ContentMachine — ONE image that runs the whole app: web + queue worker +
# scheduler (via supervisor), plus everything a render needs (Node/Remotion +
# headless Chrome, ffmpeg, yt-dlp). Self-contained: SQLite + file/db queue, so
# no external Postgres/Redis. Serves on 8080. Publish multi-arch to GHCR; deploy
# with deploy/install.sh behind Caddy.
# ────────────────────────────────────────────────────────────────────────────

# ── Stage 1: build the Vite/Tailwind assets (build-time only) ────────────────
FROM node:20-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm ci || npm install
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ── Stage 2: the combined application image ──────────────────────────────────
FROM php:8.4-cli AS app

# All system deps + extra runtimes in ONE layer (rarely changes):
#  - PHP extension libs + build tools
#  - ffmpeg (FfmpegVideoCompositor / Shorts), python3 + yt-dlp (aggregator)
#  - Node 20 (Remotion CLI renders clips)
#  - the shared libraries Remotion's headless Chrome needs at runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip supervisor ca-certificates curl ffmpeg \
        python3 python3-pip \
        libsqlite3-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
        libnss3 libdbus-1-3 libatk1.0-0 libgbm1 libasound2 libxrandr2 libxkbcommon0 \
        libxfixes3 libxcomposite1 libxdamage1 libatk-bridge2.0-0 libpango-1.0-0 libcairo2 libcups2 \
        fonts-liberation \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_sqlite bcmath pcntl zip intl gd mbstring \
    && pip3 install --no-cache-dir --break-system-packages yt-dlp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app

# Remotion deps + its headless browser FIRST — the slow, one-time download. Only
# re-runs when remotion/package*.json changes, not when app code changes.
COPY remotion/package.json remotion/package-lock.json* ./remotion/
RUN cd remotion && (npm ci || npm install) && npx remotion browser ensure

# PHP deps next (lockfiles only → editing code doesn't reinstall).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# App source LAST + compiled assets from stage 1.
COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev

ENV APP_ENV=production \
    APP_DEBUG=false \
    VAULT_PATH=/app/vault

COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
