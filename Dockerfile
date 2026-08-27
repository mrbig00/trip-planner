FROM php:8.5-fpm

# Install system deps and PHP extensions required by Laravel
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Raise PHP's upload limits above the app's own document cap
# (config('documents.max_upload_kb')) — the stock image defaults to 2M/8M.
# Keep the 12M here equal to config('documents.infra_max_upload_kb') —
# Show::addDocument() checks the two against each other at upload time and
# throws if they've drifted, so update both together.
RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 12M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Production dependencies only
RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && composer clear-cache

# Build frontend assets (if you build in CI, you may copy built files instead)
RUN if [ -f package.json ]; then \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm ci \
    && npm run build \
    && rm -rf node_modules; \
    fi

# Permissions for Laravel (in /app for build; at runtime we sync to /var/www/html)
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
