# syntax=docker/dockerfile:1

# ============================================================
# Etapa 1 — construir os assets (Vite + Tailwind)
# ============================================================
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ============================================================
# Etapa 2 — imagem PHP (aplicação + fila + agendador)
# ============================================================
FROM php:8.4-fpm AS app

# Dependências de sistema + extensões PHP
# python3 + yt-dlp: agregador multi-plataforma (metadados-only, sem descarregar média).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev libpng-dev libonig-dev \
        python3 python3-pip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql bcmath pcntl zip intl gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pip3 install --no-cache-dir --break-system-packages yt-dlp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Limites de upload (vídeos longos até 2 GB)
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependências PHP primeiro (cache de camadas)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copiar a aplicação
COPY . .

# Assets compilados vindos da etapa 1
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/php/entrypoint.sh

ENTRYPOINT ["docker/php/entrypoint.sh"]
CMD ["php-fpm"]

# ============================================================
# Etapa 3 — imagem AUTOSSUFICIENTE (single-container) para deploy
# ------------------------------------------------------------
# É esta a imagem publicada no GHCR e usada pelo ContentMachine-deploy
# (install.sh): serve HTTP em :8080 (nginx → php-fpm) e corre o worker de
# fila num só contentor, em /app, com SQLite — sem Postgres/Redis/Nginx à
# parte. A etapa 'app' (php-fpm puro) mantém-se para o docker-compose local.
# ============================================================
FROM app AS deploy

# Servidor web + gestor de processos (+ curl para o HEALTHCHECK).
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Mover a app para /app — contrato do instalador: os volumes persistentes são
# montados em /app/storage, /app/vault e /app/database. Os caminhos do Laravel
# são dinâmicos (base_path), por isso mover é seguro.
RUN mv /var/www/html /app
WORKDIR /app

COPY docker/nginx/deploy.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/deploy-entrypoint.sh /usr/local/bin/deploy-entrypoint.sh
RUN chmod +x /usr/local/bin/deploy-entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

# Defaults de produção autossuficientes (sem serviços externos). O instalador
# passa apenas APP_URL/ASSET_URL/WATCHTOWER_*; o resto assenta nestes.
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    CACHE_STORE=database \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=database \
    VAULT_PATH=/app/vault \
    APP_PORT=8080

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/deploy-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
