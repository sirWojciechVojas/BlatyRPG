FROM php:7.4-fpm-alpine

# Instalacja zależności systemowych i rozszerzeń PHP
RUN apk add --no-cache \
    icu-dev \
    zlib-dev \
    libzip-dev \
    && docker-php-ext-install \
    mysqli \
    pdo_mysql \
    intl \
    zip

# Instalacja Composer (opcjonalnie, przyda się w kontenerze)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html