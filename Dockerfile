# syntax=docker/dockerfile:1

FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libyaml-dev \
        libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
COPY . .

RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

EXPOSE 8080

CMD ["php", "bin/serve.php", "0.0.0.0:8080"]