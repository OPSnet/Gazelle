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
        mariadb-client \
        nodejs \
        nginx \
        python3 \
        python3-pip \
        python3-setuptools \
        python3-wheel \
        netcat-openbsd \
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

RUN pip3 install chardet eac-logchecker xld-logchecker

COPY . /var/www

RUN chown -R gazelle:gazelle /var/www \
    && cp /var/www/.docker/web/php.ini /etc/php/7.3/cli/php.ini \
    && cp /var/www/.docker/web/php.ini /etc/php/7.3/fpm/php.ini \
    && cp /var/www/.docker/web/xdebug.ini /etc/php/7.3/mods-available/xdebug.ini \
    && cp /var/www/.docker/web/www.conf /etc/php/7.3/fpm/pool.d/www.conf \
    && cp /var/www/.docker/web/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default

USER gazelle

RUN composer --version \
    && composer install --no-dev --optimize-autoloader --no-suggest

USER root

EXPOSE 80
CMD ["/bin/bash", "/var/www/.docker/web/entrypoint.sh"]
