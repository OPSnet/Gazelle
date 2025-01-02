FROM postgres:17

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    default-libmysqlclient-dev \
    postgresql-17-mysql-fdw \
  && apt-get autoremove \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

ADD ./misc/docker/pg/* /docker-entrypoint-initdb.d/
