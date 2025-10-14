# build with cd <repo> && docker build -f .docker/phpstan.Dockerfile -t gazelle-phpstan .

FROM ghcr.io/phpstan/phpstan:2.1.31-php8.4

RUN apk add gmp-dev patch \
  && docker-php-ext-install mysqli pcntl bcmath gmp \
  && rm -rf /var/cache/apk/* /var/tmp/* /tmp/*
