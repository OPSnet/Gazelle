FROM debian:bullseye-slim

WORKDIR /var/www

ENV DEBIAN_FRONTEND noninteractive
ENV PHP_VER=8.2

# Software package layer
# Nodesource setup comes after yarnpkg because it runs `apt-get update`
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apt-transport-https \
        build-essential \
        ca-certificates \
        curl \
        gnupg2 \
    && curl -sL https://packages.sury.org/php/apt.gpg | apt-key add - \
    && echo "deb https://packages.sury.org/php/ bullseye main" | tee /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        make \
        mktorrent \
        nginx \
        netcat \
        php${PHP_VER}-cli \
        php${PHP_VER}-curl \
        php${PHP_VER}-fpm \
        php${PHP_VER}-gd \
        php${PHP_VER}-mbstring \
        php${PHP_VER}-memcached \
        php${PHP_VER}-mysql \
        php${PHP_VER}-pgsql \
        php${PHP_VER}-xml \
        php${PHP_VER}-zip \
        php${PHP_VER}-apcu \
        php${PHP_VER}-dev \
        php${PHP_VER}-bcmath \
        php${PHP_VER}-gmp \
        php${PHP_VER}-xdebug \
        python3 \
        python3-pip \
        python3-setuptools \
        python3-wheel \
        software-properties-common \
        unzip \
        zlib1g-dev \
    && curl -fsSL https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list \
    && curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y --no-install-recommends \
        nodejs \
        yarn \
    && apt-get autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.5.1 /usr/bin/composer /usr/local/bin/composer

# Python tools layer
RUN pip3 install chardet eac-logchecker xld-logchecker

# Puppeteer layer
# This installs the necessary packages to run the bundled version of chromium for puppeteer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        gconf-service \
        libasound2 \
        libatk1.0-0 \
        libc6 \
        libcairo2 \
        libcups2 \
        libdbus-1-3 \
        libexpat1 \
        libfontconfig1 \
        libgcc1 \
        libgconf-2-4 \
        libgdk-pixbuf2.0-0 \
        libglib2.0-0 \
        libgtk-3-0 \
        libnspr4 \
        libpango-1.0-0 \
        libpangocairo-1.0-0 \
        libstdc++6 \
        libx11-6 \
        libx11-xcb1 \
        libxcb1 \
        libxcomposite1 \
        libxcursor1 \
        libxdamage1 \
        libxext6 \
        libxfixes3 \
        libxi6 \
        libxrandr2 \
        libxrender1 \
        libxss1 \
        libxtst6 \
        fonts-liberation \
        libnss3 \
        lsb-release \
        xdg-utils \
    && apt-get autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# If running Docker >= 1.13.0 use docker run's --init arg to reap zombie processes, otherwise
# uncomment the following lines to have `dumb-init` as PID 1
# ADD https://github.com/Yelp/dumb-init/releases/download/v1.2.0/dumb-init_1.2.0_amd64 /usr/local/bin/dumb-init
# RUN chmod +x /usr/local/bin/dumb-init
# ENTRYPOINT ["dumb-init", "--"]

# Uncomment to skip the chromium download when installing puppeteer. If you do,
# you'll need to launch puppeteer with:
#     browser.launch({executablePath: 'google-chrome-unstable'})
# ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD true

# testing layer
# backports needed for sphinx
# sphinx needs its config that cannot be passed through a gitlab CI service
# hence it is installed in this container
RUN echo 'deb http://deb.debian.org/debian bullseye-backports main' > /etc/apt/sources.list.d/backports.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        postgresql-client \
        default-mysql-client \
        libmemcached-tools \
        sphinxsearch \
        # all below is needed until running cypress headless/electron-less is fixed: https://github.com/cypress-io/cypress/issues/23636
        libgtk2.0-0 \
        libgtk-3-0 \
        libnotify-dev \
        libgconf-2-4 \
        libgbm-dev \
        libnss3 \
        libxss1 \
        libasound2 \
        libxtst6 \
        procps \
        #xauth \
        xvfb \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir /opt/bin \
    && curl -fsSL https://phar.phpunit.de/phpcov-8.2.1.phar --output /opt/bin/phpcov \
    && chmod +x /opt/bin/phpcov \
    && sed -i 's|START=no|START=yes|' /etc/default/sphinxsearch

# add firefox for cypress
# command from https://github.com/cypress-io/cypress-docker-images/blob/master/browsers/node16.16.0-chrome107-ff107/Dockerfile
#RUN curl --output /tmp/firefox.tar.bz2 https://download-installer.cdn.mozilla.net/pub/firefox/releases/107.0/linux-x86_64/en-US/firefox-107.0.tar.bz2 \
#    && tar -C /opt -xjf /tmp/firefox.tar.bz2 \
#    && rm /tmp/firefox.tar.bz2 \
#    && ln -fs /opt/firefox/firefox /usr/bin/firefox

COPY .docker /var/www/.docker

# Permissions and configuration layer
RUN useradd -ms /bin/bash gazelle \
    && touch /var/log/php_error.log /var/log/xdebug.log \
    && chown -R gazelle:gazelle /var/www /var/log/php_error.log /var/log/xdebug.log \
    && cp /var/www/.docker/web/php.ini /etc/php/${PHP_VER}/cli/10-php.ini \
    && cp /var/www/.docker/web/php-cli.ini /etc/php/${PHP_VER}/cli/20-cli.ini \
    && cp /var/www/.docker/web/php.ini /etc/php/${PHP_VER}/fpm/php.ini \
    && cp /var/www/.docker/web/xdebug.ini /etc/php/${PHP_VER}/mods-available/xdebug.ini \
    && sed -i 's|xdebug.mode=debug|\0,coverage|' /etc/php/${PHP_VER}/mods-available/xdebug.ini \
    && cp /var/www/.docker/web/www.conf /etc/php/${PHP_VER}/fpm/pool.d/www.conf \
    && cp /var/www/.docker/web/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir /opt/gazelle

COPY mysql_schema.sql mysql_data.sql /opt/gazelle/

EXPOSE 80/tcp

ENTRYPOINT [ "/bin/bash", "/var/www/.docker/web/entrypoint-testing.sh" ]
