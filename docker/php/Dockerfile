FROM php:8.5-fpm-bookworm

# Instalacja zależności systemowych i rozszerzeń PHP
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    && docker-php-ext-install \
    mysqli \
    pdo_mysql \
    intl \
    mbstring \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Keep backend/.env at 0640 while allowing PHP-FPM to read the host-owned file.
ARG APP_ENV_GID=82
RUN groupadd --non-unique --gid "${APP_ENV_GID}" app-env-read \
    && usermod --append --groups app-env-read www-data

# Instalacja Composer (opcjonalnie, przyda się w kontenerze)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY handout-uploads.ini /usr/local/etc/php/conf.d/handout-uploads.ini
COPY app-entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
