FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" curl mbstring pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --chown=www-data:www-data . .

USER www-data

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "index.php"]
