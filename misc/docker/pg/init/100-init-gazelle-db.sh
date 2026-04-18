#!/bin/bash

set -euo pipefail

psql -v ON_ERROR_STOP=1 --host /var/run/postgresql --username "$POSTGRES_USER" --dbname postgres <<EOSQL
create role ${POSTGRES_DB_USER} with password '${POSTGRES_USER_PASSWORD}' login;
create database ${POSTGRES_DATABASE} with owner ${POSTGRES_DB_USER};

\c ${POSTGRES_DATABASE}

create extension mysql_fdw;
grant all on foreign data wrapper mysql_fdw to ${POSTGRES_DB_USER};
EOSQL
