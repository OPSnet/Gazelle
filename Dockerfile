FROM debian:buster-slim

WORKDIR /var/www

RUN useradd -ms /bin/bash gazelle \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        ca-certificates \
        curl \
        software-properties-common \
        wget

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash - \
    && apt-get install -y --no-install-recommends \
        cron \
        git \
        imagemagick \
        libboost-dev \
        libbz2-dev \
        libssl-dev \
        libsqlite3-dev \
        libtcmalloc-minimal4 \
        make \
        nodejs \
        nginx \
        python3 \
        python3-pip \
        python3-setuptools \
        python3-wheel \
        netcat-openbsd \
        sphinxsearch \
        unzip \
        zlib1g-dev

RUN apt-get install -y --no-install-recommends \
        php7.3-cli \
        php7.3-curl \
        php7.3-fpm \
        php7.3-gd \
        php7.3-mbstring \
        php7.3-mysql \
        php7.3-xml \
        php7.3-zip \
        php-apcu \
        php-memcached \
        php-xdebug \
        composer

RUN echo 'mariadb-server mysql-server/root_password password em%G9Lrey4^N' | debconf-set-selections \
    && echo 'mariadb-server mysql-server/root_password_again password em%G9Lrey4^N' | debconf-set-selections \
    && apt-get install -y --no-install-recommends mariadb-server mariadb-client \
    && service mysql start \
    && mysql -uroot -pem%G9Lrey4^N -e "CREATE DATABASE gazelle CHARACTER SET utf8 COLLATE utf8_swedish_ci;" \
    && mysql -uroot -pem%G9Lrey4^N -e "CREATE USER 'gazelle'@'%' IDENTIFIED BY 'password';" \
    && mysql -uroot -pem%G9Lrey4^N -e "GRANT ALL ON *.* TO 'gazelle'@'%';" \
    && mysql -uroot -pem%G9Lrey4^N -e "FLUSH PRIVILEGES;" \
    # turn off strict mode because Gazelle / Ocelot do not like good DB schemas
    && sed -i -e 's/#skip-external-locking/#skip-external-locking\n\nsql_mode = ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION/' /etc/mysql/mariadb.conf.d/50-server.cnf

RUN pip3 install chardet eac-logchecker xld-logchecker

COPY . /var/www

RUN chown -R gazelle:gazelle /var/www \
    && cp /var/www/.docker/php.ini /etc/php/7.3/cli/php.ini \
    && cp /var/www/.docker/php.ini /etc/php/7.3/fpm/php.ini \
    && cp /var/www/.docker/xdebug.ini /etc/php/7.3/mods-available/xdebug.ini \
    && cp /var/www/.docker/www.conf /etc/php/7.3/fpm/pool.d/www.conf \
    && cp /var/www/.docker/config.php /var/www/classes/config.php \
    && cp /var/www/.docker/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && cp /var/www/.docker/sphinx.conf /etc/sphinxsearch/sphinx.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default

RUN su gazelle -c "composer --version" \
    && su gazelle -c "composer install" \
    && su gazelle -c "composer dump-autoload" \
    && service mysql start \
    && su gazelle -c "vendor/bin/phinx migrate" \
    && su gazelle -c "vendor/bin/phinx seed:run -s InitialUserSeeder"

RUN echo "START=yes" | tee /etc/default/sphinxsearch > /dev/null \
    && rm -rf /var/lib/apt/lists/* \
    && crontab /var/www/.docker/crontab \
    && service mysql start \
    && service sphinxsearch start \
    && indexer -c /etc/sphinxsearch/sphinx.conf --all

#USER gazelle

EXPOSE 80

CMD ["/bin/bash", "/var/www/.docker/init.sh"]
