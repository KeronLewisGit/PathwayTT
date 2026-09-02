# syntax=docker/dockerfile:1.7
#
# PathwayTT — local preview / development image.
#
# Production stays a plain shared-host LAMP deployment (docs/SPEC.md); this
# image mirrors that shape (Apache + PHP 8.3, MySQL alongside) so what you
# see in Docker is what cPanel will run. See docs/DOCKER.md.
#
# Stages:
#   base    PHP 8.3 + Apache + the extensions the app needs
#   vendor  Composer install (cached separately from source changes)
#   assets  Node build of resources/css + resources/js via Vite
#   app     Everything assembled

ARG PHP_VERSION=8.3
ARG NODE_VERSION=20

# ── base ──────────────────────────────────────────────────────────────
FROM php:${PHP_VERSION}-apache AS base

ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip curl \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd exif opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-pathwaytt.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ── vendor ────────────────────────────────────────────────────────────
FROM base AS vendor

# INSTALL_DEV=true keeps Pest & friends so `docker compose exec app php artisan test` works.
ARG INSTALL_DEV=true

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-interaction --prefer-dist --no-progress \
        --no-scripts --no-autoloader \
        $( [ "$INSTALL_DEV" = "true" ] || echo "--no-dev" )

# ── assets ────────────────────────────────────────────────────────────
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
# Tailwind scans Laravel's pagination views for class names.
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ── app ───────────────────────────────────────────────────────────────
FROM base AS app

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && php artisan filament:upgrade --ansi \
    # Configuration comes from the container environment; an (empty) .env
    # still needs to exist because parts of the toolchain read it directly.
    && printf '# Configuration is supplied by the container environment (docker-compose.yml).\n' > .env \
    && mkdir -p storage/app/private storage/app/public storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
