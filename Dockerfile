FROM debian:bookworm-slim

ENV DEB_RELEASE=bookworm
ENV DEBIAN_FRONTEND=noninteractive
ENV PHP_VER=8.3
ENV NODE_VERSION=20

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
    && curl -sL https://packages.sury.org/php/apt.gpg | apt-key add - \
    && echo "deb https://packages.sury.org/php/ $DEB_RELEASE main" | tee /etc/apt/sources.list.d/php.list \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
        make \
        nginx \
        netcat-openbsd \
        nodejs \
        php${PHP_VER}-cli \
        php${PHP_VER}-common \
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
        php${PHP_VER}-xdebug \
        python3 \
        python3-pip \
        python3-setuptools \
        python3-wheel \
        software-properties-common \
        unzip \
        yarn \
        zlib1g-dev \
        # Puppeteer layer
        # This installs the necessary packages to run the bundled version of chromium for puppeteer
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
    && rm -rf /var/lib/apt/lists/* \
    # Python tools layer
    && pip3 install --break-system-packages chardet eac-logchecker xld-logchecker

COPY misc/docker/ /var/www/misc/docker
COPY lib /var/www/lib
COPY bin/ /var/www/bin
COPY --from=composer:2.7.7 /usr/bin/composer /usr/local/bin/composer

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
    && perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/torrent 2 100 \
    && perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/riplog 2 100 \
    && perl /var/www/bin/generate-storage-dirs /var/lib/gazelle/riploghtml 2 100 \
    && chown -R gazelle:gazelle /var/lib/gazelle /var/www \
    && npm install -g npm@10.8.2

EXPOSE 80/tcp
EXPOSE 3306/tcp
EXPOSE 34000/tcp

ENTRYPOINT [ "/bin/bash", "/var/www/misc/docker/web/entrypoint.sh" ]
