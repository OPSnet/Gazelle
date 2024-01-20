FROM ghcr.io/phpstan/phpstan:latest-php8.2                                                                                                                        

RUN apk add gmp-dev patch \
  && docker-php-ext-install mysqli pcntl bcmath gmp \
  && rm -rf /var/cache/apk/* /var/tmp/* /tmp/*
