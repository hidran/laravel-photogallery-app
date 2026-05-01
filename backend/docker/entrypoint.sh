#!/bin/sh
set -e

cd /var/www/html

# Cache config + routes at runtime so production env vars are captured.
# Build-time caching captures the local-dev defaults instead.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Start supervisor (nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
