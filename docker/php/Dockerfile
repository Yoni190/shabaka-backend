FROM php:8.4-fpm

# System deps + PHP extensions + nginx + supervisor
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libpng-dev libonig-dev \
    nginx supervisor \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd mbstring bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# --- Nginx & Supervisor configuration ---
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Render ignores EXPOSE, but it's good practice
EXPOSE 10000

ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g ${GID} www-data

# IMPORTANT: ownership is applied AFTER the UID change,
# so storage/ and bootstrap/cache/ end up owned by the
# same UID that PHP-FPM workers run as.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Start supervisor (which starts both nginx and php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]