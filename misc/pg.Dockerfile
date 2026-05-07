FROM postgres:18

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    default-libmysqlclient-dev \
    postgresql-18-mysql-fdw \
    postgresql-18-pgvector \
  && apt-get autoremove \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY ./misc/docker/pg/init/ /docker-entrypoint-initdb.d/
