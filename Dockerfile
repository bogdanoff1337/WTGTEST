FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libsqlite3-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite zip bcmath intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN git config --global --add safe.directory /app

WORKDIR /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
