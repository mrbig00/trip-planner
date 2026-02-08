# Production Laravel image using FrankenPHP (single process: Caddy + PHP)
FROM serversideup/php:8.5-frankenphp

USER root

# Install PHP extensions required by Laravel (PostgreSQL, zip, bcmath)
RUN install-php-extensions \
    pdo_pgsql \
    zip \
    bcmath

# Install Node.js for frontend build (LTS 20.x)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer deps and build assets in a single layer to keep image smaller
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && composer clear-cache

COPY . .

# Laravel package discovery (requires APP_KEY; generate for build)
RUN php -r "file_exists('.env') || copy('.env.example', '.env');" \
    && php artisan key:generate --no-interaction \
    && php artisan package:discover --ansi

# Build frontend assets
RUN if [ -f package.json ]; then \
    npm ci \
    && npm run build \
    && rm -rf node_modules; \
    fi

# Laravel storage and cache permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create storage link at build time (as root); autorun would fail as www-data (public/ owned by root)
RUN php artisan storage:link

USER www-data

# FrankenPHP serves from /var/www/html/public by default (CADDY_SERVER_ROOT)
# Expose HTTP 8080 and HTTPS 8443 (use -p 80:8080 -p 443:8443 when running)
EXPOSE 8080 8443
