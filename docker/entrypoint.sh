#!/bin/sh
set -e
# On first run, shared volume is empty — copy built app from image into it
if [ ! -f /var/www/html/artisan ]; then
  cp -a /app/. /var/www/html/
  chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
  chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
fi
exec "$@"
