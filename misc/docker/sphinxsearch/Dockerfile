FROM debian:buster-slim

ENV SPHINX_VERSION 2.2.11

ENV SPHINX_FULL_STRING ${SPHINX_VERSION}-release

RUN apt-get update && apt-get install -y --no-install-recommends \
        cron \
        curl \
        default-libmysqlclient-dev \
        mariadb-client \
        sphinxsearch \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -pv /var/lib/sphinxsearch/data/ /var/lib/sphinxsearch/conf/

VOLUME /var/lib/sphinxsearch/data/
VOLUME /var/lib/sphinxsearch/conf/

COPY crontab /var/lib/sphinxsearch/conf/
COPY entrypoint.sh /var/lib/sphinxsearch/conf/

# redirect logs to stdout
RUN ln -sv /dev/stdout /var/log/sphinxsearch/query.log \
    && ln -sv /dev/stdout /var/log/sphinxsearch/searchd.log

EXPOSE 36307

ENTRYPOINT [ "/bin/bash", "/var/lib/sphinxsearch/conf/entrypoint.sh" ]
