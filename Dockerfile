# =============================================================================
# Unified production Dockerfile for Quome CRM
# Runs: nginx + php-fpm + redis + queue worker + scheduler via supervisord
# Requires: external MySQL database (provided via env vars)
# =============================================================================

# --- Stage 1: Build frontend assets ---
FROM node:18-alpine AS frontend
WORKDIR /build
COPY crm/package.json crm/package-lock.json* ./
RUN npm install --no-audit
COPY crm/ ./
RUN npm run build

# --- Stage 2: Install PHP dependencies ---
FROM php:8.2-cli AS composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y git unzip libzip-dev libicu-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install zip intl gd mbstring bcmath calendar pdo_mysql pcntl exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
WORKDIR /build
COPY crm/composer.json crm/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader
COPY crm/ ./
RUN composer dump-autoload --optimize --no-dev

# --- Stage 3: Runtime ---
FROM php:8.2-fpm

# Install system deps + nginx + redis + supervisor
RUN apt-get update && apt-get install -y \
    nginx \
    redis-server \
    supervisor \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        calendar \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Nginx config — remove ALL default configs to avoid conflicts
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default /etc/nginx/conf.d/default.conf
COPY docker/prod/nginx.conf /etc/nginx/conf.d/app.conf

# Supervisor config
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Redis config (bind to localhost only)
RUN mkdir -p /var/run/redis && chown redis:redis /var/run/redis

# Copy application code
WORKDIR /var/www/html
COPY crm/ ./

# Copy built vendor from composer stage
COPY --from=composer /build/vendor ./vendor

# Copy built frontend assets from frontend stage
COPY --from=frontend /build/public/build ./public/build

# Remove cached bootstrap files that reference dev-only packages
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php

# Copy entrypoint
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Create storage directories structure
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord"]
