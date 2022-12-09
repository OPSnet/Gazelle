#!/bin/bash

set -euo pipefail

LIB_DIR="${CI_PROJECT_DIR}/lib"
SOURCE="${LIB_DIR}/devel.example.config.php"
TARGET="${LIB_DIR}/override.config.php"

[ -f ${TARGET} ] && exit 0
echo "GENERATING GAZELLE CONFIG..."
(
    perl -ple 's/""/q{"} . qx(dd if=\/dev\/urandom count=1 bs=12 status=none | base64 | tr -d \\\\n) . q{"}/e' "${SOURCE}"
    date +"define('SITE_LAUNCH_YEAR', %Y);"
) > "${TARGET}"

grep -q SERVER_ROOT "$TARGET" || echo "define('SERVER_ROOT', '$CI_PROJECT_DIR');" >> "${TARGET}"
# mysql
grep -q SQLHOST "$TARGET" || echo "define('SQLHOST', '$MYSQL_HOST');" >> "${TARGET}"
grep -q SQLDB "$TARGET" || echo "define('SQLDB', '$MYSQL_DATABASE');" >> "${TARGET}"
grep -q SQLLOGIN "$TARGET" || echo "define('SQLLOGIN', '$MYSQL_USER');" >> "${TARGET}"
grep -q SQLPASS "$TARGET" || echo "define('SQLPASS', '$MYSQL_PASSWORD');" >> "${TARGET}"
sed -i "s|sc5tlc9JSCC6|$MYSQL_ROOT_PASSWORD|g" "${TARGET}"
# postgres
grep -q GZPG_HOST "$TARGET" || echo "define('GZPG_HOST', '$PGHOST');" >> "${TARGET}"
grep -q GZPG_DB "$TARGET" || echo "define('GZPG_DB', '$POSTGRES_DATABASE');" >> "${TARGET}"
grep -q GZPG_USER "$TARGET" || echo "define('GZPG_USER', '$POSTGRES_DB_USER');" >> "${TARGET}"
grep -q GZPG_PASSWORD "$TARGET" || echo "define('GZPG_PASSWORD', '$POSTGRES_USER_PASSWORD');" >> "${TARGET}"
# memcached
grep -q CACHE_ID "$TARGET" || echo "define('CACHE_ID', '$MEMCACHED_NAMESPACE');" >> "${TARGET}"
# sphinx
grep -q SPHINX_HOST "$TARGET" || echo "define('SPHINX_HOST', '127.0.0.1');" >> "${TARGET}"
