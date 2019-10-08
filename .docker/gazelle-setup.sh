#!/usr/bin/env bash

#######
# This script, and the contents of this directory, are to be used primarily for setting
# up and building a Vagrant environment in Debian Jessie. While you could use this script
# as a starting point for a production server, you must be aware that you need to edit
# the files here, in at least the following ways (but also probably others):
#   1) Don't use eatmydata before any apt-gets if you care about the server's files
#   2) READ ALL PARTS OF config.php TO KNOW WHAT IT DOES AND CONTROLS!!
#   3) Make sure to edit config.php to have unique values for the passwords and secrets
#   4) Turn off DEBUG_MODE, and set DISABLE_TRACKER and DISABLE_IRC to false if using them
#   5) Modify your server's php.ini file to what you want, you don't need the one in .vagrant
#
# And no, config.php is not the config.php that we run on the live Orpheus site.
#######


# This script is intended to be run as root.
if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root"
    exit
fi

if [ -f ~/.runonce ]
then
    echo "Gazelle setup already run, skipping..."
    exit
fi
touch ~/.runonce

export DEBIAN_FRONTEND=noninteractive
echo "deb http://ftp.debian.org/debian stretch-backports main" >> /etc/apt/sources.list

# Don't do this in production
apt-get install -y eatmydata

eatmydata apt-get update

# install basic stuff that we need for potential later operations
eatmydata apt-get install -y software-properties-common build-essential make libssl-dev zlib1g-dev libbz2-dev libsqlite3-dev libboost-dev libtcmalloc-minimal4 unzip wget curl netcat-openbsd imagemagick
curl -sL https://deb.nodesource.com/setup_10.x | bash -
eatmydata apt-get install -y nodejs

# TODO: Remove default mailer, install nullmailer and PostOffice
# We can remove the default MTA (exim4) as it doesn't do anything that really helps us on our sever. However, we do
# want to install sendmail (but not let it run) so that PHP can send mail.
# eatmydata apt-get remove -y exim4 exim4-base exim4-config exim4-daemon-light
# rm -rf /var/log/exim4

eatmydata apt-get install -y git nginx memcached nodejs
eatmydata apt-get install -y python3
wget https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
python3 /tmp/get-pip.py
rm -f /tmp/get-pip.py
pip3 install -U pip
pip3 install chardet eac-logchecker xld-logchecker

eatmydata apt-get install -y sphinxsearch
eatmydata apt-get install -y php7.0 php7.0-fpm php7.0-mysql php7.0-cli php7.0-gd php7.0-curl php7.0-mbstring php7.0-xml php7.0-zip php-memcached php-xdebug php-apcu

debconf-set-selections <<< 'mariadb-server mysql-server/root_password password em%G9Lrey4^N'
debconf-set-selections <<< 'mariadb-server mysql-server/root_password_again password em%G9Lrey4^N'
eatmydata apt-get install -y mariadb-server mariadb-client

npm install --global --unsafe-perm puppeteer
chmod -R o+rx /usr/lib/node_modules/puppeteer/.local-chromium

pushd /var/www/sections/tools/development
npm link puppeteer
popd

rm -r /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-available/default
cp /var/www/.vagrant/nginx.conf /etc/nginx/sites-available/gazelle.conf
cp /var/www/.vagrant/php.ini /etc/php/7.0/cli/php.ini
cp /var/www/.vagrant/php.ini /etc/php/7.0/fpm/php.ini
cp /var/www/.vagrant/xdebug.ini /etc/php/7.0/mods-available/xdebug.ini
cp /var/www/.vagrant/www.conf /etc/php/7.0/fpm/pool.d/www.conf
cp /var/www/.vagrant/config.php /var/www/classes/config.php

ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf

#mysql -uroot -pem%G9Lrey4^N < /var/www/.vagrant/gazelle.sql
mysql -uroot -pem%G9Lrey4^N -e "CREATE DATABASE gazelle CHARACTER SET utf8 COLLATE utf8_swedish_ci;"
mysql -uroot -pem%G9Lrey4^N -e "CREATE USER 'gazelle'@'%' IDENTIFIED BY 'password';"
mysql -uroot -pem%G9Lrey4^N -e "GRANT ALL ON *.* TO 'gazelle'@'%';"
mysql -uroot -pem%G9Lrey4^N -e "FLUSH PRIVILEGES;"
sed -i "s/^bind-address/\# bind-address/" /etc/mysql/my.cnf
sed -i "s/^skip-external-locking/\# skip-external-locking/" /etc/mysql/my.cnf

# Setup composer
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
EXPECTED_SIGNATURE=$(wget -q -O - https://composer.github.io/installer.sig)
ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', '/tmp/composer-setup.php');")
if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
    >&2 echo 'ERROR: Invalid installer signature'
else
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    su vagrant -c "composer --version"
    pushd /var/www
    su vagrant -c "composer install"
    su vagrant -c "composer dump-autoload"
    su vagrant -c "vendor/bin/phinx migrate"
    su vagrant -c "vendor/bin/phinx seed:run -s InitialUserSeeder"
    popd
fi
rm -f /tmp/composer-setup.php

echo "START=yes" | tee /etc/default/sphinxsearch > /dev/null
cp /var/www/.vagrant/sphinx.conf /etc/sphinxsearch/sphinx.conf
indexer -c /etc/sphinxsearch/sphinx.conf --all

cp /var/www/.vagrant/init.d/* /etc/init.d
chmod +x /etc/init.d/memcached.sock
update-rc.d memcached.sock defaults

crontab /var/www/.vagrant/crontab

if [ -d /var/ocelot ]; then
    cp /var/www/.vagrant/config.cpp /var/ocelot/config.cpp
    #cd /var/ocelot
    #./configure
    #make
    #screen -S ocelot -dm /var/ocelot/ocelot
fi;

service mysql restart
service memcached.sock restart
service sphinxsearch restart
service php7.0-fpm restart
service nginx restart
