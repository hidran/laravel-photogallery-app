# ==============================================================
# PhotoGallery Pro — Production Container
# Multi-stage: composer install → final image with PHP-FPM + Nginx
# ==============================================================

# Stage 1: Install Composer dependencies
# Use --ignore-platform-reqs because composer:2 image lacks ext-intl, ext-gd etc.
# The final production stage installs them all and runs autoload regeneration.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --no-autoloader \
    --ignore-platform-reqs

# Stage 2: Production image
FROM php:8.4-fpm-alpine AS production

# Install system deps + PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        exif \
        pdo_mysql \
        mbstring \
        intl \
        opcache \
        pcntl \
    && rm -rf /var/cache/apk/*

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"
COPY docker/opcache.ini "$PHP_INI_DIR/conf.d/10-opcache.ini"
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Supervisor config (runs both nginx + php-fpm)
COPY docker/supervisord.conf /etc/supervisord.conf

# Install Composer binary so we can regenerate autoload in this stage
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App code
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

# Regenerate optimized autoloader now that all PHP extensions are present
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Storage directories
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Entrypoint: cache config/routes at runtime so prod env vars are captured.
# Build-time caching captures local-dev defaults from .env (wrong DB driver, etc.).
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
