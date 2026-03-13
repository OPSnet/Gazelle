FROM debian:bookworm-slim

ENV DEB_RELEASE=bookworm
ENV DEBIAN_FRONTEND=noninteractive
ENV PHP_VER=8.5
ENV NODE_VERSION=20
# https://github.com/composer/getcomposer.org/commits/main/
# commit of composer 2.9.3
ENV COMPOSER_COMMIT=681b090a0890b986ee0ae5862ca98db7c9170144

# Uncomment to skip the chromium download when installing puppeteer. If you do,
# you'll need to launch puppeteer with:
#     browser.launch({executablePath: 'google-chrome-unstable'})
# ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD true

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apt-transport-https \
        build-essential \
        ca-certificates \
        curl \
        gnupg2 \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://packages.sury.org/php/apt.gpg > /usr/share/keyrings/debsuryorg-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $DEB_RELEASE main" > /etc/apt/sources.list.d/php.list \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        make \
        mktorrent \
        nginx \
        netcat-openbsd \
        nodejs \
        php${PHP_VER}-cli \
        php${PHP_VER}-curl \
        php${PHP_VER}-fpm \
        php${PHP_VER}-gd \
        php${PHP_VER}-iconv \
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
        php-xdebug \
        python3 \
        python3-pip \
        python3-setuptools \
        python3-wheel \
        software-properties-common \
        unzip \
        zlib1g-dev \
        # This installs the necessary packages to run the bundled version of chromium for puppeteer
        fonts-liberation \
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
        libnss3 \
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
        lsb-release \
        xdg-utils \
    && apt-get autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://raw.githubusercontent.com/composer/getcomposer.org/$COMPOSER_COMMIT/web/installer \
      | php -- --quiet \
    && mv composer.phar /usr/local/bin/composer \
    # Python tools layer
    && pip3 install --break-system-packages chardet eac-logchecker xld-logchecker

# testing layer
# backports needed for sphinx
# sphinx needs its config that cannot be passed through a gitlab CI service
# hence it is installed in this container
RUN echo "deb http://deb.debian.org/debian ${DEB_RELEASE}-backports main" > /etc/apt/sources.list.d/backports.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        libmemcached-tools \
        postgresql-client \
        sphinxsearch \
        # all below is needed until running cypress headless/electron-less is fixed: https://github.com/cypress-io/cypress/issues/23636
        libgtk2.0-0 \
        libnotify-dev \
        libgbm-dev \
        procps \
        xvfb \
    && apt-get autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's/START=no/START=yes/' /etc/default/sphinxsearch

# add firefox for cypress
# command from https://github.com/cypress-io/cypress-docker-images/blob/master/browsers/node16.16.0-chrome107-ff107/Dockerfile
#RUN curl --output /tmp/firefox.tar.bz2 https://download-installer.cdn.mozilla.net/pub/firefox/releases/107.0/linux-x86_64/en-US/firefox-107.0.tar.bz2 \
#    && tar -C /opt -xjf /tmp/firefox.tar.bz2 \
#    && rm /tmp/firefox.tar.bz2 \
#    && ln -fs /opt/firefox/firefox /usr/bin/firefox

COPY misc/docker /var/www/misc/docker

# Permissions and configuration layer
RUN useradd -ms /bin/bash gazelle \
    && touch /var/log/php_error.log /var/log/xdebug.log \
    && chown -R gazelle:gazelle /var/www /var/log/php_error.log /var/log/xdebug.log \
    && cp /var/www/misc/docker/web/php.ini /etc/php/${PHP_VER}/cli/10-php.ini \
    && cp /var/www/misc/docker/web/php.ini /etc/php/${PHP_VER}/fpm/php.ini \
    && cp /var/www/misc/docker/web/php-cli.ini /etc/php/${PHP_VER}/cli/20-cli.ini \
    && sed 's/xdebug.mode=.*/xdebug.mode=coverage/' /var/www/misc/docker/web/xdebug.ini > /etc/php/${PHP_VER}/mods-available/xdebug.ini \
    && cp /var/www/misc/docker/web/www.conf /etc/php/${PHP_VER}/fpm/pool.d/www.conf \
    && cp /var/www/misc/docker/web/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir /opt/gazelle

COPY mysql_schema.sql mysql_data.sql /opt/gazelle/

EXPOSE 80/tcp

ENTRYPOINT [ "/bin/bash", "/var/www/misc/docker/web/entrypoint-testing.sh" ]
