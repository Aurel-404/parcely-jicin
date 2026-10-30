#!/bin/sh
set -e

# The project directory is bind-mounted, so install Composer dependencies on the first start.
# The install also writes the class map of src/ (composer.json "classmap"). With it the autoloader needs no
# file_exists() per class, which costs ~9 ms each on a bind mount from the Windows file system (measured),
# i.e. hundreds of ms per request. It is not regenerated on every start (that takes 23 s on such a mount);
# a class added later is still found through PSR-4, "composer dump-autoload" adds it to the map.
if [ ! -f /app/vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

# var/ is a named volume (created root-owned); PHP-FPM workers run as www-data and write the tile cache
# (var/tiles) and the error log (var/log/app.log) there.
mkdir -p /app/var/tiles /app/var/log
chown www-data:www-data /app/var /app/var/tiles /app/var/log

exec docker-php-entrypoint "$@"
