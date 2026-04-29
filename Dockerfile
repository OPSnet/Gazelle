FROM debian:trixie-slim

ENV DEB_RELEASE=trixie
ENV DEBIAN_FRONTEND=noninteractive
ENV PHP_VER=8.5
# https://github.com/composer/getcomposer.org/commits/main/
# commit of composer 2.9.5
ENV COMPOSER_COMMIT=09a1f131c28d6c496f6bddcbf6cebf34502ad9bc

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
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
        git  \
        make \
        nginx \
        netcat-openbsd \
        nodejs \
        npm \
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
        shellcheck \
        unzip \
        zlib1g-dev \
        # This installs the necessary packages to run the bundled version of chromium for puppeteer
        fonts-liberation \
        libasound2 \
        libatk1.0-0 \
        libc6 \
        libcairo2 \
        libcups2 \
        libdbus-1-3 \
        libexpat1 \
        libfontconfig1 \
        libgcc1 \
        libgdk-pixbuf-xlib-2.0-0 \
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
        # cypress
        # all below is needed until running cypress headless/electron-less is fixed: https://github.com/cypress-io/cypress/issues/23636
        libgbm-dev \
        libgtk2.0-0 \
        libnotify-dev \
        procps \
        xvfb \
    && apt-get autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://raw.githubusercontent.com/composer/getcomposer.org/$COMPOSER_COMMIT/web/installer \
      | php -- --quiet \
    && mv composer.phar /usr/local/bin/composer \
    # Python tools layer
    && pip3 install --break-system-packages chardet eac-logchecker xld-logchecker

COPY misc/docker/ /var/www/misc/docker
COPY lib /var/www/lib
COPY bin/ /var/www/bin

# Permissions and configuration layer
RUN useradd -ms /bin/bash gazelle \
    && cp /var/www/misc/docker/web/php.ini /etc/php/${PHP_VER}/cli/php.ini \
    && cp /var/www/misc/docker/web/php.ini /etc/php/${PHP_VER}/fpm/php.ini \
    && cp /var/www/misc/docker/web/xdebug.ini /etc/php/${PHP_VER}/mods-available/xdebug.ini \
    && cp /var/www/misc/docker/web/www.conf /etc/php/${PHP_VER}/fpm/pool.d/www.conf \
    && cp /var/www/misc/docker/web/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && echo "Initialize Boris..." \
    && grep '^disable_functions' /etc/php/${PHP_VER}/cli/php.ini \
        | sed -r 's/pcntl_(fork|signal|signal_dispatch|waitpid),//g' \
        > /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini \
    && echo "Generate file storage directories..." \
    && perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/riplog 2 100 \
    && perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/riploghtml 2 100 \
    && perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/torrent 2 100 \
    && chown -R gazelle:gazelle /var/lib/gazelle /var/www

EXPOSE 80/tcp
EXPOSE 3306/tcp
EXPOSE 34000/tcp

ENTRYPOINT [ "/bin/bash", "/var/www/misc/docker/web/entrypoint.sh" ]
