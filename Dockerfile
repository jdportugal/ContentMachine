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
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev libpng-dev libonig-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql bcmath pcntl zip intl gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

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
