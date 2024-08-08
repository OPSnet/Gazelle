#!/bin/bash

set -euo pipefail

run_service()
{
    service "$1" start || exit 1
}

# initialize sphinx
cp "${CI_PROJECT_DIR}/misc/docker/sphinxsearch/sphinx.conf" /etc/sphinxsearch/sphinx.conf
sed -i "s|\(sql_user = \).*|\1${MYSQL_USER}|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|\(sql_pass = \).*|\1${MYSQL_PASSWORD}|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|\(sql_db = \).*|\1${MYSQL_DATABASE}|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|/var/lib/sphinxsearch/data/|${CI_PROJECT_DIR}/.sphinxsearch/|g" /etc/sphinxsearch/sphinx.conf
sed -i "s|listen = |\0127.0.0.1:|g" /etc/sphinxsearch/sphinx.conf
mkdir -p "${CI_PROJECT_DIR}/.sphinxsearch"
chown sphinxsearch:sphinxsearch "${CI_PROJECT_DIR}/.sphinxsearch"
setpriv --reuid sphinxsearch -- /usr/bin/indexer --all

echo "Start services..."

run_service sphinxsearch
run_service nginx
run_service "php${PHP_VER}-fpm"
