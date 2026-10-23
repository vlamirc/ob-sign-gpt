# syntax=docker/dockerfile:1
FROM php:8.5-apache-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libicu-dev unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql mbstring gd zip intl bcmath pcntl \
    && a2enmod rewrite headers remoteip setenvif \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY deploy/droplet/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/droplet/php.ini /usr/local/etc/php/conf.d/assina.ini

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts \
    && composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi

FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY --from=dependencies /var/www/html ./
RUN npm ci && npm run build

FROM php-base AS app
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /build/public/build /var/www/html/public/build
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
CMD ["apache2-foreground"]
