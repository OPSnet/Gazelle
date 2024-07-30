#!/bin/bash

set -euo pipefail

run_service()
{
    service "$1" start || exit 1
}

if [ ! -e .docker-init-done ] ; then
    .docker/web/generate-config.sh
    composer --version
    composer install --no-progress --optimize-autoloader
    bin/local-patch
    yarn
    npx update-browserslist-db@latest
    yarn dev
    touch .docker-init-done
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

echo "Start services..."

run_service cron
run_service nginx
run_service php${PHP_VER}-fpm

crontab /var/www/.docker/web/crontab

tail -f /var/log/nginx/access.log
