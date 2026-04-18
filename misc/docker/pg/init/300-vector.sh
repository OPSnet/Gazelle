#!/bin/bash

set -euo pipefail

psql -v ON_ERROR_STOP=1 --host /var/run/postgresql --username "$POSTGRES_USER" --dbname "$POSTGRES_DATABASE" <<EOSQL
create extension vector;
EOSQL
