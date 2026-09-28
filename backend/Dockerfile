# Local development image. Source code is bind-mounted at runtime (see docker-compose.yml
# at the repo root) so edits on the host are picked up immediately — this image only
# provides the PHP runtime + extensions and Composer; dependencies are installed by
# docker/entrypoint.sh on first start (or reused if backend/vendor already exists from a
# native `composer install`).
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install intl pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
