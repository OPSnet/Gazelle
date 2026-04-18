#!/bin/bash

set -euo pipefail

psql -v ON_ERROR_STOP=1 --host /var/run/postgresql --username "$POSTGRES_USER" --dbname "$POSTGRES_DATABASE" <<EOSQL
create role ${PG_RO_USER} with password '${PG_RO_PASS}' login;
grant connect on database ${POSTGRES_DATABASE} to ${PG_RO_USER};
grant pg_read_all_data to ${PG_RO_USER};
alter default privileges for role ${POSTGRES_DB_USER} in schema public grant select on tables to ${PG_RO_USER};
EOSQL
