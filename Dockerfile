FROM php:8.4-alpine AS frontend

RUN apk add --no-cache \
    nodejs \
    npm \
    icu-dev \
    libzip-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install intl zip

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev

RUN rm -f bootstrap/cache/*.php

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

RUN npm run build

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-libs \
    libzip \
    caddy

RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    icu-dev \
    libzip-dev \
    && docker-php-ext-install intl zip pdo_mysql opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --optimize-autoloader --no-dev --no-scripts

COPY --chown=www-data:www-data . .

RUN composer dump-autoload --optimize --no-dev \
    && rm -rf /usr/bin/composer

RUN rm -f bootstrap/cache/*.php

COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/app/public/cpt-templates && \
    if [ -d resources/cpt-templates ]; then \
        cp -r resources/cpt-templates/* storage/app/public/cpt-templates/ 2>/dev/null || true; \
    fi

RUN php artisan storage:link

RUN php artisan filament:assets

RUN php artisan config:cache

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

RUN rm -rf \
    /var/www/html/tests \
    /var/www/html/.git \
    /var/www/html/node_modules \
    /var/www/html/resources/js \
    /var/www/html/resources/css \
    /var/www/html/package.json \
    /var/www/html/package-lock.json \
    /var/www/html/vite.config.ts \
    /var/www/html/tsconfig.json \
    /var/www/html/tailwind.config.js \
    /var/www/html/phpunit.xml \
    /var/www/html/.phpunit.result.cache \
    /var/www/html/eslint.config.js \
    /var/www/html/prettier.config.js \
    /var/www/html/components.json \
    /var/www/html/database/factories

COPY --chown=www-data:www-data Caddyfile /etc/caddy/Caddyfile

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 80 443

COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]