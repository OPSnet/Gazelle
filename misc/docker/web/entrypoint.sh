#!/bin/bash

set -euo pipefail

run_service()
{
    service "$1" start || exit 1
}

if [ ! -e .docker-init-done ] ; then
    "$(dirname $0)"/generate-config.sh
    composer --version
    composer install --no-progress --optimize-autoloader
    bin/local-patch
    echo "Installing node, go grab a coffee"
    bin/config-css /tmp/config-css.js
    npm install -g npm@11.11.1
    npm install cypress
    npx update-browserslist-db@latest
    npx puppeteer browsers install chrome
    bin/config-css /tmp/config-css.js
    npm run dev
    touch .docker-init-done
fi

while ! nc -z mysql 3306
do
    echo "Waiting for MySQL..."
    sleep 10
done

PHINXBIN=/var/www/vendor/bin/phinx
echo "Phase 1 Mysql migrations..."
if ! FKEY_MY_DATABASE=1 LOCK_MY_DATABASE=1 $PHINXBIN migrate -e gazelle; then
    echo "Fatal error in the phase 1 Mysql migrations"
    exit 1
fi

echo "Postgresql migrations..."
if ! $PHINXBIN migrate -c ./misc/phinx-pg.php; then
    echo "Fatal error in the Postgresql migrations"
    exit 1
fi

echo "Phase 2 Mysql migrations..."
if ! $PHINXBIN migrate -c ./misc/my2-phinx.php; then
    echo "Fatal error in the phase 2 Mysql migrations"
    exit 1
fi

# restart the web container if it is left running for more than a year!
openssl req -x509 -newkey ed25519 \
    -noenc -keyout /etc/nginx/selfsigned.key -out /etc/nginx/selfsigned.crt \
    -subj "/CN=gazelle.local" \
    -days 365
nginx -t

mkdir -p /var/www/cache/twig
chown -R gazelle:gazelle /var/log/xdebug.log /var/www/cache/twig

echo "Start services..."

run_service cron
run_service nginx
run_service php${PHP_VER}-fpm

crontab /var/www/misc/docker/web/crontab

tail -f /var/log/nginx/access.log
