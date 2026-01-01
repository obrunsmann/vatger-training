#!/bin/sh

# Cache configuration for production
if [ "$APP_ENV" = "production" ]; then
    php artisan package:discover --ansi
    php artisan config:cache
fi

# Start PHP-FPM
php-fpm -D

# Start Caddy
caddy run --config /etc/caddy/Caddyfile --adapter caddyfile