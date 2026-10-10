# ---- Stage 1: build frontend assets with Vite ----
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

# ---- Stage 2: PHP + Apache runtime ----
FROM php:8.4-apache

# System deps + PHP extensions Laravel needs (incl. pdo_mysql, gd for dompdf, zip, intl)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev libicu-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
    pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
  && echo "=== MPM modules before ===" && ls /etc/apache2/mods-enabled/ | grep mpm || true \
  && a2dismod mpm_event mpm_worker || true \
  && rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
  && a2enmod mpm_prefork rewrite headers \
  && echo "=== MPM modules after ===" && ls /etc/apache2/mods-enabled/ | grep mpm || true \
  && echo "=== apache2ctl -M ===" && apache2ctl -M 2>&1 | grep -i mpm || true \
  && echo "=== configtest ===" && apache2ctl configtest || true \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache docroot -> Laravel public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Install PHP deps first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

# Copy the rest of the app
COPY . .

# Built Vite assets from stage 1
COPY --from=assets /app/public/build ./public/build

# Finish Laravel install steps + permissions
RUN composer dump-autoload --optimize \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
