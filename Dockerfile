# Build stage for frontend assets with PHP
FROM php:8.4-alpine AS frontend

RUN apk add --no-cache nodejs npm \
    icu-dev \
    libzip-dev \
    zip \
    curl \
    git

RUN docker-php-ext-install intl zip

WORKDIR /app

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer \
    && composer --version

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev

RUN rm -f bootstrap/cache/*.php

RUN npm ci

RUN npm run build

# Production stage with Caddy
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    libzip-dev \
    zip \
    postgresql-dev \
    icu-dev \
    curl \
    git \
    caddy \
    && docker-php-ext-install intl zip pdo_mysql pdo_pgsql

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer

COPY composer.json composer.lock ./

RUN composer install --optimize-autoloader --no-dev --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-dev

RUN rm -f bootstrap/cache/*.php

COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/app/public/cpt-templates && \
    if [ -d resources/cpt-templates ]; then \
        cp -r resources/cpt-templates/* storage/app/public/cpt-templates/ 2>/dev/null || true; \
    fi

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY --chown=www-data:www-data docker/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80 443

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]