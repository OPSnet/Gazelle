# build with docker build -f misc/phpstan.Dockerfile -t gazelle-phpstan .
# see https://github.com/phpstan/phpstan/pkgs/container/phpstan/versions?filters%5Bversion_type%5D=tagged

FROM ghcr.io/phpstan/phpstan:2.1.40-php8.4

RUN apk add gmp-dev patch \
  && docker-php-ext-install mysqli pcntl bcmath gmp \
  && rm -rf /var/cache/apk/* /var/tmp/* /tmp/*
