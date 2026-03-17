#!/bin/bash

set -euo pipefail

# Set the following to 1 in order to dump the mysql schema after all the migrations have run.
# This can help in debugging migrations.
DUMP_MYSQL_SCHEMA=0

cd "${CI_PROJECT_DIR}"
export COMPOSER_HOME="${CI_PROJECT_DIR}/.composer"

[ -f "${CI_PROJECT_DIR}/lib/override.config.php" ] || bash "${CI_PROJECT_DIR}/misc/docker/web/generate-config-testing.sh"
sed -i 's|gazelle\.php|ci-coverage.php|' "${CI_PROJECT_DIR}/public/index.php"

composer --version && composer install --no-progress
bin/local-patch

cat > ~/.my.cnf <<EOF
[client]
skip-ssl = true
user = $MYSQL_USER
password = $MYSQL_PASSWORD
host = $MYSQL_HOST
database = $MYSQL_DATABASE
EOF
chmod 600 ~/.my.cnf

while ! nc -z "$MYSQL_HOST" 3306
do
    echo "Waiting for MySQL..."
    sleep 1
done

echo "Configure postgres database..."
cat > ~/.pgpass <<EOF
#hostname:port:database:username:password
${PGHOST}:5432:*:${POSTGRES_USER}:${POSTGRES_PASSWORD}
${PGHOST}:5432:*:${POSTGRES_DB_USER}:${POSTGRES_USER_PASSWORD}
EOF
chmod 600 ~/.pgpass

if [ -z "${MYSQL_INIT_DB-}" ]; then
    echo "Restore mysql dump..."
    (
        cat /opt/gazelle/mysql_schema.sql /opt/gazelle/mysql_data.sql
        cat <<EOF
CREATE USER IF NOT EXISTS 'ro_$MYSQL_USER'@'%' IDENTIFIED BY 'ro_$MYSQL_PASSWORD';
GRANT SELECT ON $MYSQL_DATABASE.* TO 'ro_$MYSQL_USER'@'%';
GRANT SELECT ON performance_schema.table_io_waits_summary_by_index_usage TO '$MYSQL_USER'@'%';
GRANT SELECT ON performance_schema.table_io_waits_summary_by_table TO '$MYSQL_USER'@'%';
GRANT SELECT ON sys.schema_redundant_indexes TO '$MYSQL_USER'@'%';
GRANT SELECT ON sys.schema_unused_indexes TO '$MYSQL_USER'@'%';
GRANT SELECT ON sys.x\$schema_flattened_keys TO '$MYSQL_USER'@'%';
GRANT SELECT ON performance_schema.table_io_waits_summary_by_index_usage TO 'ro_$MYSQL_USER'@'%';
GRANT SELECT ON performance_schema.table_io_waits_summary_by_table TO 'ro_$MYSQL_USER'@'%';
GRANT SELECT ON sys.schema_redundant_indexes TO 'ro_$MYSQL_USER'@'%';
GRANT SELECT ON sys.schema_unused_indexes TO 'ro_$MYSQL_USER'@'%';
GRANT SELECT ON sys.x\$schema_flattened_keys TO 'ro_$MYSQL_USER'@'%';
CREATE FUNCTION IF NOT EXISTS binomial_ci(p int, n int)
  RETURNS float DETERMINISTIC
  RETURN IF(n = 0,0.0,((p + 1.35336) / n - 1.6452 * SQRT((p * (n-p)) / n + 0.67668) / n) / (1 + 2.7067 / n));
CREATE FUNCTION IF NOT EXISTS bonus_accrual(Size bigint, Seedtime float, Seeders integer)
  RETURNS float DETERMINISTIC NO SQL
  RETURN Size / pow(1024, 3) * (0.0433 + (0.07 * ln(1 + Seedtime/24)) / pow(greatest(Seeders, 1), 0.35));
CREATE FUNCTION IF NOT EXISTS category_bonus_accrual(size bigint, seedtime float, seeders integer, scale float)
  RETURNS float DETERMINISTIC NO SQL
  RETURN (size / scale) / pow(1024, 3) * (0.0433 + (0.07 * ln(1 + seedtime/24)) / pow(greatest(seeders, 1), 0.35));
EOF
    ) | mysql -u root -p"$MYSQL_ROOT_PASSWORD" || exit 1
fi

PHINXBIN="${CI_PROJECT_DIR}/vendor/bin/phinx"
echo "Phase 1 Mysql migrations..."
if ! FKEY_MY_DATABASE=1 LOCK_MY_DATABASE=1 $PHINXBIN migrate -e gazelle; then
    echo "Fatal error in the phase 1 Mysql migrations"
    exit 1
fi

echo "Postgresql migrations..."
if ! $PHINXBIN migrate -c ./misc/phinx-pg.php; then
    echo "Fatal error in the Postgresql migrations"
    exit 1
fi

echo "Phase 2 Mysql migrations..."
if ! $PHINXBIN migrate -c ./misc/my2-phinx.php; then
    echo "Fatal error in the phase 2 Mysql migrations"
    exit 1
fi

if [ "${DUMP_MYSQL_SCHEMA}" = 1 ]; then
    mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" -f --single-transaction --no-data --databases "$MYSQL_DATABASE"
fi

if [ ! -d /var/lib/gazelle/torrent ]; then
    echo "Generate file storage directories..."
    perl "${CI_PROJECT_DIR}/bin/generate-storage-dirs" /var/lib/gazelle/general 1 100
    perl "${CI_PROJECT_DIR}/bin/generate-storage-dirs" /var/lib/gazelle/riplog 2 100
    perl "${CI_PROJECT_DIR}/bin/generate-storage-dirs" /var/lib/gazelle/riploghtml 2 100
    perl "${CI_PROJECT_DIR}/bin/generate-storage-dirs" /var/lib/gazelle/torrent 2 100
    chown -R gazelle /var/lib/gazelle
fi

# configure nginx
sed -i "s|/var/www|${CI_PROJECT_DIR}|g" /etc/nginx/sites-available/gazelle.conf
