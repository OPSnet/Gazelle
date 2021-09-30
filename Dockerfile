FROM debian:buster-slim

WORKDIR /var/www

# Software package layer
# Nodesource setup comes after yarnpkg because it runs `apt-get update`
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apt-transport-https \
        build-essential \
        ca-certificates \
        gnupg2 \
        wget \
    && wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add - \
    && echo "deb https://packages.sury.org/php/ buster main" | tee /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
        curl \
        git \
        imagemagick \
        libboost-dev \
        libbz2-dev \
        libssl-dev \
        libsqlite3-dev \
        libtcmalloc-minimal4 \
        make \
        mariadb-client \
        netcat-openbsd \
        nginx \
        php8.0 \
        php8.0-cli \
        php8.0-curl \
        php8.0-fpm \
        php8.0-gd \
        php8.0-mbstring \
        php8.0-mysql \
        php8.0-xml \
        php8.0-zip \
        php-apcu \
        php-bcmath \
        php-memcached \
        php-xdebug \
        python3 \
        python3-pip \
        python3-setuptools \
        python3-wheel \
        software-properties-common \
        unzip \
        wget \
        zlib1g-dev \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list \
    && curl -sL https://deb.nodesource.com/setup_12.x | bash - \
    && apt-get install -y --no-install-recommends \
        nodejs \
        yarn \
    && apt-get autoremove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.1 /usr/bin/composer /usr/local/bin/composer

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
        libappindicator1 \
        libnss3 \
        lsb-release \
        xdg-utils \
    && apt-get autoremove \
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

COPY . /var/www

# Permissions and configuration layer
RUN useradd -ms /bin/bash gazelle \
    && chown -R gazelle:gazelle /var/www \
    && cp /var/www/.docker/web/php.ini /etc/php/8.0/cli/php.ini \
    && cp /var/www/.docker/web/php.ini /etc/php/8.0/fpm/php.ini \
    && cp /var/www/.docker/web/xdebug.ini /etc/php/8.0/mods-available/xdebug.ini \
    && cp /var/www/.docker/web/www.conf /etc/php/8.0/fpm/pool.d/www.conf \
    && cp /var/www/.docker/web/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default

EXPOSE 80/tcp
EXPOSE 3306/tcp
EXPOSE 34000/tcp

ENTRYPOINT [ "/bin/bash", "/var/www/.docker/web/entrypoint.sh" ]
