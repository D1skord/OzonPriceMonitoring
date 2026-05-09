FROM node:20-bookworm-slim AS node

FROM php:8.4-fpm-bookworm

ARG TIMEZONE=Europe/Moscow
ARG UID=1000
ARG GID=1000

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/bin/npm /usr/local/bin/npm
COPY --from=node /usr/local/bin/npx /usr/local/bin/npx
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN rm -f /usr/local/bin/npm /usr/local/bin/npx \
    && ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY docker/php/php.ini /usr/local/etc/php/conf.d/ozon-prices.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
    acl ca-certificates chromium cron fluxbox git g++ \
    libfreetype6-dev libicu-dev libjpeg-dev libonig-dev libpng-dev libpq-dev libzip-dev \
    novnc procps unzip websockify x11vnc xvfb zlib1g-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install gd intl mbstring pcntl pdo_pgsql pgsql zip \
    && ln -snf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo ${TIMEZONE} > /etc/timezone \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN node --version && npm --version && npx --version

RUN groupadd --gid ${GID} app \
    && useradd --uid ${UID} --gid app --shell /bin/bash --create-home app

WORKDIR /var/www/ozon-prices

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts \
    && PLAYWRIGHT_BROWSERS_PATH=/ms-playwright vendor/bin/playwright-install --browsers \
    && chown -R app:app /ms-playwright
