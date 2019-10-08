#!/usr/bin/env bash

echo "Start services..."
service cron start
service mysql start
service nginx start
service php7.3-fpm start

echo "Run migrate..."
/var/www/vendor/bin/phinx migrate -e docker

if [ ! -f /srv/gazelle.txt ]; then
    touch /srv/gazelle.txt
    /var/www/vendor/bin/phinx seed:run -e docker -s InitialUserSeeder
fi

service sphinxsearch start

tail -f /var/log/nginx/access.log
