FROM php:8.4-fpm

ARG APP_TIMEZONE=Europe/Belgrade
ENV TZ=${APP_TIMEZONE}

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    tzdata \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

CMD ["php-fpm"]
