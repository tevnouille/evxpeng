FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        intl \
        opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader --no-interaction

COPY . .

RUN find . -type d -exec chmod 755 {} \; \
    && find . -type f -exec chmod 644 {} \; \
    && chmod +x artisan \
    && composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
