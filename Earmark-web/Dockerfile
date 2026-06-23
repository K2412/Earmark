FROM node:22-bookworm AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN WAYFINDER_COMMAND=true npm run build

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_sqlite zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/earmark-entrypoint

RUN chmod +x /usr/local/bin/earmark-entrypoint \
    && mkdir -p database storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 8000

ENTRYPOINT ["earmark-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
