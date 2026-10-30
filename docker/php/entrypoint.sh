#!/bin/sh
set -e

# The project directory is bind-mounted, so install Composer dependencies on the first start.
if [ ! -f /app/vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

# var/ is a named volume (created root-owned); PHP-FPM workers run as www-data and write the tile cache there.
chown www-data:www-data /app/var

exec docker-php-entrypoint "$@"
