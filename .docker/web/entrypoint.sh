#!/bin/bash

PHP_VER=8.1

run_service()
{
    service "$1" start || exit 1
}

# We'll need these anyway so why not kill some time while waiting on MySQL to be ready
if [ -n "$ENV" ] && [ "$ENV" == "prod" ]; then
    su -c 'composer --version && composer install --no-progress --no-dev --optimize-autoloader --no-suggest; yarn --prod; yarn prod' gazelle
else
    su -c 'composer --version && composer install --no-progress; yarn; yarn dev' gazelle
fi

# Wait for MySQL...
counter=1
while ! mysql -h mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "show databases;" > /dev/null 2>&1; do
    sleep 1
    counter=$((counter + 1))
    if [ $((counter % 20)) -eq 0 ]; then
        mysql -h mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "show databases;"
        >&2 echo "Still waiting for MySQL (Count: $counter)."
    fi;
done

[ -f /var/www/lib/override.config.php ] || bash /var/www/.docker/web/generate-config.sh

echo "Run migrations..."
if ! FKEY_MY_DATABASE=1 LOCK_MY_DATABASE=1 /var/www/vendor/bin/phinx migrate; then
    echo "PHINX FAILED TO RUN MIGRATIONS"
    exit 1
fi

echo -e "\n"

if [ ! -f /srv/gazelle.txt ]; then
    echo "Run seed:run..."
    if ! /var/www/vendor/bin/phinx seed:run -s InitialUserSeeder; then
        echo "PHINX FAILED TO SEED"
        exit 1
    fi
    touch /srv/gazelle.txt
    echo -e "\n"
fi

if [ ! -d /var/lib/gazelle/torrent ]; then
    echo "Generate file storage directories..."
    perl /var/www/scripts/generate-storage-dirs /var/lib/gazelle/torrent 2 100
    perl /var/www/scripts/generate-storage-dirs /var/lib/gazelle/riplog 2 100
    perl /var/www/scripts/generate-storage-dirs /var/lib/gazelle/riploghtml 2 100
    chown -R gazelle /var/lib/gazelle
fi

if [ ! -f /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini ]; then
    echo "Initialize Boris..."
    grep '^disable_functions' /etc/php/${PHP_VER}/cli/php.ini \
        | sed -r 's/pcntl_(fork|signal|signal_dispatch|waitpid),//g' \
        > /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini
fi

echo "Start services..."

run_service cron
run_service nginx
run_service php${PHP_VER}-fpm

crontab /var/www/.docker/web/crontab

tail -f /var/log/nginx/access.log
