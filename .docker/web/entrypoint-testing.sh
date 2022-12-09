#!/bin/bash

set -euo pipefail

PHP_VER=8.1

run_service()
{
    service "$1" start || exit 1
}

cd "${CI_PROJECT_DIR}"
export YARN_CACHE_FOLDER="${CI_PROJECT_DIR}/.yarn-cache"
export COMPOSER_HOME="${CI_PROJECT_DIR}/.composer"
export POSTGRES_USER_PASSWORD="$(dd if=/dev/urandom count=1 bs=12 status=none | base64)"

[ -f "${CI_PROJECT_DIR}/lib/override.config.php" ] || bash "${CI_PROJECT_DIR}/.docker/web/generate-config-testing.sh"

if [ ! -f /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini ]; then
    echo "Initialize Boris..."
    grep '^disable_functions' /etc/php/${PHP_VER}/cli/php.ini \
        | sed -r 's/pcntl_(fork|signal|signal_dispatch|waitpid),//g' \
        > /etc/php/${PHP_VER}/cli/conf.d/99-boris.ini
fi

cat > ~/.my.cnf <<EOF
[client]
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

composer --version && composer install --no-progress; yarn; npx browserslist@latest --update-db; yarn dev gazelle

echo "Create postgres database..."
#hostname:port:database:username:password
echo "${PGHOST}:5432:postgres:${POSTGRES_USER}:${POSTGRES_PASSWORD}" > ~/.pgpass
echo "${PGHOST}:5432:${POSTGRES_DATABASE}:${POSTGRES_DB_USER}:${POSTGRES_USER_PASSWORD}" >> ~/.pgpass
chmod 600 ~/.pgpass
psql -U "$POSTGRES_USER" postgres -c "create role ${POSTGRES_DB_USER} with password '${POSTGRES_USER_PASSWORD}' login;"
psql -U "$POSTGRES_USER" postgres -c "create database ${POSTGRES_DATABASE} with owner ${POSTGRES_DB_USER};"

for sql in "${CI_PROJECT_DIR}"/db/pg/*.sql ; do
    psql -f "$sql"
done

if [ -z "${MYSQL_INIT_DB-}" ]; then
    echo "Restore mysql dump..."
    time mysql < /opt/gazelle/mysql_schema.sql
    time mysql < /opt/gazelle/mysql_data.sql
    echo 'CREATE FUNCTION bonus_accrual(Size bigint, Seedtime float, Seeders integer)
  RETURNS float DETERMINISTIC NO SQL
  RETURN Size / pow(1024, 3) * (0.0433 + (0.07 * ln(1 + Seedtime/24)) / pow(greatest(Seeders, 1), 0.35));
CREATE FUNCTION `binomial_ci`(p int, n int)
  RETURNS float DETERMINISTIC
  RETURN IF(n = 0,0.0,((p + 1.35336) / n - 1.6452 * SQRT((p * (n-p)) / n + 0.67668) / n) / (1 + 2.7067 / n));' \
  | mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
fi

echo "Run mysql migrations..."
if ! ( FKEY_MY_DATABASE=1 LOCK_MY_DATABASE=1 "${CI_PROJECT_DIR}/vendor/bin/phinx" migrate -e gazelle ) ; then
    echo "PHINX FAILED TO RUN MIGRATIONS"
    exit 1
fi

if [ ! -z "${MYSQL_INIT_DB-}" ]; then
    echo "Run seed:run..."
    if ! "${CI_PROJECT_DIR}/vendor/bin/phinx" seed:run; then
        echo "PHINX FAILED TO SEED"
        exit 1
    fi
fi

if [ ! -d /var/lib/gazelle/torrent ]; then
    echo "Generate file storage directories..."
    perl "${CI_PROJECT_DIR}/scripts/generate-storage-dirs" /var/lib/gazelle/torrent 2 100
    perl "${CI_PROJECT_DIR}/scripts/generate-storage-dirs" /var/lib/gazelle/riplog 2 100
    perl "${CI_PROJECT_DIR}/scripts/generate-storage-dirs" /var/lib/gazelle/riploghtml 2 100
    chown -R gazelle /var/lib/gazelle
fi

# initialize sphinx
cp "${CI_PROJECT_DIR}/.docker/sphinxsearch/sphinx.conf" /etc/sphinxsearch/sphinx.conf
sed -i "s|\(sql_user = \).*|\1${MYSQL_USER}|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|\(sql_pass = \).*|\1${MYSQL_PASSWORD}|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|\(sql_db = \).*|\1${MYSQL_DATABASE}|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|/var/lib/sphinxsearch/data/|${CI_PROJECT_DIR}/.sphinxsearch/|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|listen = |\0127.0.0.1:|g" /etc/sphinxsearch/sphinx.conf
mkdir -p "${CI_PROJECT_DIR}/.sphinxsearch"
chown sphinxsearch:sphinxsearch "${CI_PROJECT_DIR}/.sphinxsearch"
setpriv --reuid sphinxsearch -- /usr/bin/indexer --all

# configure nginx
sed -i "s|/var/www|${CI_PROJECT_DIR}|g" /etc/nginx/sites-available/gazelle.conf

echo "Start services..."

run_service sphinxsearch
run_service nginx
run_service php${PHP_VER}-fpm
