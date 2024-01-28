#!/bin/bash

set -euo pipefail

run_service()
{
    service "$1" start || exit 1
}

npm install -g npm@10.4.0
if [ "$ENV" == "prod" ]; then
    su -c 'composer --version && composer install --no-progress --no-dev --optimize-autoloader --no-suggest; npm install -g npm@10.4.0; yarn --prod; npx update-browserslist-db@latest; yarn prod' gazelle
else
    su -c 'composer --version && composer install --no-progress; yarn; npx update-browserslist-db@latest; yarn dev' gazelle
fi

[ -f /var/www/lib/override.config.php ] || bash /var/www/.docker/web/generate-config.sh

if [ ! -f /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini ]; then
    echo "Initialize Boris..."
    grep '^disable_functions' /etc/php/${PHP_VER}/cli/php.ini \
        | sed -r 's/pcntl_(fork|signal|signal_dispatch|waitpid),//g' \
        > /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini
fi

while ! nc -z mysql 3306
do
    echo "Waiting for MySQL..."
    sleep 10
done

echo "Run mysql migrations..."
if ! FKEY_MY_DATABASE=1 LOCK_MY_DATABASE=1 /var/www/vendor/bin/phinx migrate; then
    echo "PHINX FAILED TO RUN MIGRATIONS"
    exit 1
fi

echo "Run postgres migrations..."
if ! /var/www/vendor/bin/phinx migrate -c ./misc/phinx-pg.php; then
    echo "PHINX FAILED TO RUN MIGRATIONS"
    exit 1
fi

if [ ! -f /var/www/misc/phinx/seeded.txt ]; then
    echo "Run seed:run..."
    if ! /var/www/vendor/bin/phinx seed:run; then
        echo "PHINX FAILED TO SEED"
        exit 1
    fi
    echo "Seeds have been run, delete to rerun" > /var/www/misc/phinx/seeded.txt
    chmod 400 /var/www/misc/phinx/seeded.txt
fi

if [ ! -d /var/lib/gazelle/torrent ]; then
    echo "Generate file storage directories..."
    perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/torrent 2 100
    perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/riplog 2 100
    perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/riploghtml 2 100
    chown -R gazelle /var/lib/gazelle
fi

echo "Start services..."

run_service cron
run_service nginx
run_service php${PHP_VER}-fpm

crontab /var/www/.docker/web/crontab

tail -f /var/log/nginx/access.log
