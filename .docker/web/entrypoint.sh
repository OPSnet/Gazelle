#!/usr/bin/env bash

run_service()
{
    service $1 start
    if [ $? -ne 0 ]; then
        exit 1
    fi
}

# Wait for MySQL...
counter=1
while ! mysql -h mysql -ugazelle -ppassword -e "show databases;" > /dev/null 2>&1; do
    sleep 1
    counter=$((counter + 1))
    if [ $((counter % 20)) -eq 0 ]; then
        mysql -h mysql -ugazelle -ppassword -e "show databases;"
        >&2 echo "Still waiting for MySQL (Count: ${counter})."
    fi;
done

if [ ! -f /var/www/classes/config.php ]; then
    bash /var/www/.docker/web/generate-config.sh
fi

echo "Run migrate..."
LOCK_MY_DATABASE=1 /var/www/vendor/bin/phinx migrate

if [ $? -ne 0 ]; then
    echo "PHINX FAILED TO MIGRATE"
    exit 1
fi

echo -e "\n"

if [ ! -f /srv/gazelle.txt ]; then
    echo "Run seed:run..."
    /var/www/vendor/bin/phinx seed:run -s InitialUserSeeder
    if [ $? -ne 0 ]; then
        echo "PHINX FAILED TO SEED"
        exit 1
    fi
    touch /srv/gazelle.txt
    echo -e "\n"
fi

echo "Start services..."

run_service cron
run_service nginx
run_service php7.3-fpm

crontab /var/www/.docker/web/crontab

tail -f /var/log/nginx/access.log
