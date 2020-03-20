FROM debian:buster-slim

ARG BuildMode=dev

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

# Install latest chrome dev package and fonts to support major charsets (Chinese, Japanese, Arabic, Hebrew, Thai and a few others)
# Note: this installs the necessary libs to make the bundled version of Chromium that Puppeteer
# installs, work.
RUN wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add - \
    && sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list' \
    && apt-get update \
    && apt-get install -y google-chrome-unstable fonts-ipafont-gothic fonts-wqy-zenhei fonts-thai-tlwg fonts-kacst fonts-freefont-ttf \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www

RUN chown -R gazelle:gazelle /var/www \
    && cp /var/www/.docker/web/php.ini /etc/php/7.3/cli/php.ini \
    && cp /var/www/.docker/web/php.ini /etc/php/7.3/fpm/php.ini \
    && cp /var/www/.docker/web/xdebug.ini /etc/php/7.3/mods-available/xdebug.ini \
    && cp /var/www/.docker/web/www.conf /etc/php/7.3/fpm/pool.d/www.conf \
    && cp /var/www/.docker/web/nginx.conf /etc/nginx/sites-available/gazelle.conf \
    && ln -s /etc/nginx/sites-available/gazelle.conf /etc/nginx/sites-enabled/gazelle.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && groupadd -r pptruser \
    && usermod -aG pptruser,audio,video gazelle
    # Adds user so we don't need --no-sandbox.

# If running Docker >= 1.13.0 use docker run's --init arg to reap zombie processes, otherwise
# uncomment the following lines to have `dumb-init` as PID 1
# ADD https://github.com/Yelp/dumb-init/releases/download/v1.2.0/dumb-init_1.2.0_amd64 /usr/local/bin/dumb-init
# RUN chmod +x /usr/local/bin/dumb-init
# ENTRYPOINT ["dumb-init", "--"]

# Uncomment to skip the chromium download when installing puppeteer. If you do,
# you'll need to launch puppeteer with:
#     browser.launch({executablePath: 'google-chrome-unstable'})
# ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD true

USER gazelle

RUN if [ "$BuildMode" = "prod" ]; then \
        composer --version && composer install --no-dev --optimize-autoloader --no-suggest; \
        npm i --production; \
    else \
        composer --version && composer install; \
        npm i; \
    fi

USER root

EXPOSE 80/tcp
EXPOSE 3306/tcp
EXPOSE 34000/tcp

CMD ["/bin/bash", "/var/www/.docker/web/entrypoint.sh"]
