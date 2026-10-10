#!/bin/sh
set -e

# Railway provides $PORT; Apache listens on 80 by default.
# Reconfigure Apache when PORT differs (Railway healthchecks hit $PORT).
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# Cache config/routes with /tmp-safe paths already set via env
php artisan config:clear --no-interaction || true
php artisan route:clear --no-interaction || true
php artisan view:clear --no-interaction || true

# Run migrations on every deploy (safe: --force, no prompts)
php artisan migrate --force --no-interaction || {
  echo "WARNING: migrations failed; continuing to serve anyway"
}

exec apache2-foreground
