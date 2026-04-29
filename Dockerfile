# syntax=docker/dockerfile:1.4

# =============================================================================
# Stage 1: Builder - Compilation des dépendances PHP
# =============================================================================
FROM php:8.4-cli-alpine AS builder

WORKDIR /app

RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    linux-headers \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    oniguruma-dev \
    curl-dev \
    git \
    unzip

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json ./

RUN composer update \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-cache \
    --no-scripts

RUN php -r "require 'vendor/autoload.php'; echo 'Spatie: ' . (class_exists('Spatie\\Permission\\Models\\Role') ? 'OK' : 'MISSING') . PHP_EOL; echo 'Sanctum: ' . (class_exists('Laravel\\Sanctum\\HasApiTokens') ? 'OK' : 'MISSING') . PHP_EOL;"

RUN apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# =============================================================================
# Stage 2: Production Runner - Image finale optimisée
# =============================================================================
FROM php:8.4-fpm-alpine

LABEL maintainer="HRManager Team"
LABEL version="1.0"
LABEL description="HRManager Backend - Laravel API"

WORKDIR /var/www

RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    netcat-openbsd \
    redis \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    zlib \
    libxml2 \
    oniguruma \
    $PHPIZE_DEPS \
    linux-headers \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    oniguruma-dev

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        xml \
        opcache

RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN sed -i 's/^listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^;listen.allowed_clients/listen.allowed_clients/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^listen.allowed_clients = .*/listen.allowed_clients = 127.0.0.1/' /usr/local/etc/php-fpm.d/www.conf

COPY --from=builder /app/vendor /var/www/vendor

COPY --chown=www-data:www-data . /var/www

# ✅ Correction : copier composer dans le stage final
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN rm -f composer.lock && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN mkdir -p \
    /var/www/storage/app/public \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    /var/log/supervisor \
    /run/nginx \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 755 /run/nginx

RUN apk del --purge $PHPIZE_DEPS autoconf make gcc g++ linux-headers \
    libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev zlib-dev libxml2-dev oniguruma-dev \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/* /usr/src/* \
    && docker-php-source delete

COPY docker/nginx/conf.d/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:80/api/health || exit 1

EXPOSE 80 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
