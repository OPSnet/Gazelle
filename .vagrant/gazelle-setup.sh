#!/usr/bin/env bash

if [ -f ~/.runonce ]
then
    echo "Gazelle setup already run, skipping..."
    exit
fi
touch ~/.runonce

export DEBIAN_FRONTEND=noninteractive

# Don't do this in production
sudo apt-get install -y eatmydata


# Add source for getting PHP 7.0
sudo eatmydata apt-get install -qy software-properties-common
sudo add-apt-repository 'deb http://packages.dotdeb.org jessie all'
wget https://www.dotdeb.org/dotdeb.gpg -O /tmp/dotdeb.gpg
sudo apt-key add /tmp/dotdeb.gpg
rm -f /tmp/dotdeb.gpg

sudo eatmydata apt-get update

# install basic stuff that we need for potential later operations
sudo eatmydata apt-get install -y make build-essential libssl-dev zlib1g-dev libbz2-dev libsqlite3-dev libboost-dev libtcmalloc-minimal4 unzip wget curl netcat-openbsd imagemagick

sudo echo "deb http://ftp.debian.org/debian jessie-backports main" >> /etc/apt/sources.list
# Add the i386 architecture so we can install wine32 which is needed to run the eac_log_checker.exe
sudo dpkg --add-architecture i386
curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -
sudo eatmydata apt-get update

# We can remove the default MTA (exim4) as it doesn't do anything that really helps us on our sever. However, we do
# want to install sendmail (but not let it run) so that PHP can send mail.
sudo eatmydata apt-get remove -y exim4 exim4-base exim4-config exim4-daemon-light
sudo rm -rf /var/log/exim4
sudo eatmydata apt-get install -y sendmail-bin
sudo service sendmail stop
sudo update-rc.d sendmail remove

sudo eatmydata apt-get install -y git nginx memcached nodejs

sudo eatmydata apt-get install -y python3 python3-pip
sudo pip3 install -U pip
sudo pip3 install chardet

sudo eatmydata apt-get install -y sphinxsearch
sudo eatmydata apt-get install -y php7.0 php7.0-fpm php7.0-memcached php7.0-mcrypt php7.0-mysqlnd php7.0-cli php7.0-xdebug php7.0-gd php7.0-curl php7.0-mbstring php7.0-xml php7.0-zip

debconf-set-selections <<< 'mariadb-server mysql-server/root_password password em%G9Lrey4^N'
debconf-set-selections <<< 'mariadb-server mysql-server/root_password_again password em%G9Lrey4^N'
sudo eatmydata apt-get install -y mariadb-server mariadb-client

sudo eatmydata apt-get install -y wine wine32

# We set the global area for NPM since puppeteer cannot install normally globally
# even with sudo =S
su vagrant <<'EOF'
npm config set prefix '/home/vagrant/.npm-global'
echo "export PATH=/home/vagrant/.npm-global/bin:$PATH" >> /home/vagrant/.profile
source /home/vagrant/.profile
npm install -g puppeteer
pushd /var/www//sections/tools/development
npm link puppeteer
popd
EOF

rm -r /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-available/default
sudo cp /var/www/.vagrant/nginx.conf /etc/nginx/sites-available/gazelle.conf
sudo cp /var/www/.vagrant/php.ini /etc/php/7.0/cli/php.ini
sudo cp /var/www/.vagrant/php.ini /etc/php/7.0/fpm/php.ini
sudo cp /var/www/.vagrant/xdebug.ini /etc/php/7.0/mods-available/xdebug.ini
sudo cp /var/www/.vagrant/www.conf /etc/php/7.0/fpm/pool.d/www.conf

ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf

#mysql -uroot -pem%G9Lrey4^N < /var/www/.vagrant/gazelle.sql
mysql -uroot -pem%G9Lrey4^N -e "CREATE DATABASE gazelle CHARACTER SET utf8 COLLATE utf8_swedish_ci;"
mysql -uroot -pem%G9Lrey4^N -e "CREATE USER 'gazelle'@'%' IDENTIFIED BY 'password';"
mysql -uroot -pem%G9Lrey4^N -e "GRANT ALL ON *.* TO 'gazelle'@'%';"
mysql -uroot -pem%G9Lrey4^N -e "FLUSH PRIVILEGES;"
sudo sed -i "s/^bind-address/\# bind-address/" /etc/mysql/my.cnf
sudo sed -i "s/^skip-external-locking/\# skip-external-locking/" /etc/mysql/my.cnf

# Setup composer
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
EXPECTED_SIGNATURE=$(wget -q -O - https://composer.github.io/installer.sig)
ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', '/tmp/composer-setup.php');")
if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
    >&2 echo 'ERROR: Invalid installer signature'
    rm /tmp/composer-setup.php
else
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    sudo -u vagrant composer --version
    pushd /var/www
    sudo -u vagrant composer install
    sudo -u vagrant composer dump-autoload
    sudo -u vagrant vendor/bin/phinx migrate -e apollo
    popd
    rm /tmp/composer-setup.php
fi

echo "START=yes" | sudo tee /etc/default/sphinxsearch > /dev/null
sudo cp /var/www/.vagrant/sphinx.conf /etc/sphinxsearch/sphinx.conf
sudo indexer -c /etc/sphinxsearch/sphinx.conf --all

sudo cp /var/www/.vagrant/init.d/* /etc/init.d
sudo chmod +x /etc/init.d/memcached.sock
sudo update-rc.d memcached.sock defaults

sudo cp /var/www/.vagrant/config.php /var/www/classes/config.php
sudo cp /var/www/.vagrant/crontab /etc/cron.d/

if [ -d /var/ocelot ]; then
    sudo cp /var/www/.vagrant/config.cpp /var/ocelot/config.cpp
    #cd /var/ocelot
    #./configure
    #make
    #screen -S ocelot -dm /var/ocelot/ocelot
fi;

sudo service mysql restart
sudo service memcached.sock restart
sudo service sphinxsearch restart
sudo service php7.0-fpm restart
sudo service nginx restart
