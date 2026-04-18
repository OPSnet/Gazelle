FROM postgres:17

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    default-libmysqlclient-dev \
    postgresql-17-mysql-fdw \
    postgresql-17-pgvector \
  && apt-get autoremove \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY ./misc/docker/pg/init/ /docker-entrypoint-initdb.d/
