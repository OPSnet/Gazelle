#!/usr/bin/env bash

# Wait for MySQL...
counter=1
while ! mysql -h mysql -ugazelle -ppassword -e "show databases;" > /dev/null 2>&1; do
    sleep 1
    counter=`expr $counter + 1`
    if [ $(($counter % 20)) -eq 0 ]; then
        mysql -h mysql -ugazelle -ppassword -e "show databases;"
        >&2 echo "Still waiting for MySQL (Count: ${counter})."
    fi;
done

if [ ! -f /srv/www/classes/config.php ]; then
    cp /var/www/.docker/web/config.php /var/www/classes/config.php
fi

echo "Run migrate..."
LOCK_MY_DATABASE=1 /var/www/vendor/bin/phinx migrate

if [ ! -f /srv/gazelle.txt ]; then
    touch /srv/gazelle.txt
    /var/www/vendor/bin/phinx seed:run -s InitialUserSeeder
fi

echo "Start services..."
service cron start
service nginx start
service php7.3-fpm start

crontab /var/www/.docker/web/crontab

tail -f /var/log/nginx/access.log
