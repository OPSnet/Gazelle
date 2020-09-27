#!/usr/bin/env bash

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

if [ -f ${THIS_DIR}/../../classes/config.php ]; then
    exit 0;
fi

echo "GENERATING GAZELLE CONFIG..."
echo ""
cp ${THIS_DIR}/../../classes/config.template.php ${THIS_DIR}/../../classes/config.php
CONF_FILE=${THIS_DIR}/../../classes/config.php
sed -i -e "6s/''/'Gazelle Dev'/" ${CONF_FILE}
sed -i -e "7s/''/'localhost:8080'/" ${CONF_FILE}
sed -i -e "8s/''/'localhost:8080'/" ${CONF_FILE}

sed -i -e "10s/''/'localhost'/" ${CONF_FILE}
sed -i -e "11s|'https://'.SITE_HOST|'http://localhost:8080'|" ${CONF_FILE}
sed -i -e "12s|/path|/var/www|g" ${CONF_FILE}
sed -i -e "13s|/path|/var/www|g" ${CONF_FILE}
sed -i -e "15s|''|'http://localhost:34000'|" ${CONF_FILE}
sed -i -e "16s|''|'https://localhost:3400'|" ${CONF_FILE}

sed -i -e "37s/localhost/mysql/" ${CONF_FILE}
sed -i -e "38s/''/'${MYSQL_USER}'/" ${CONF_FILE}
sed -i -e "39s/''/'${MYSQL_PASSWORD}'/" ${CONF_FILE}
sed -i -e "40s/''/'${MYSQL_USER}'/" ${CONF_FILE}
sed -i -e "41s/''/'${MYSQL_PASSWORD}'/" ${CONF_FILE}
sed -i -e "49s/.*/    ['host' => 'memcached', 'port' => 11211]/" ${CONF_FILE}

sed -i -e "53s/localhost/sphinxsearch/" ${CONF_FILE}
sed -i -e "s/127.0.0.1/sphinxsearch/" ${CONF_FILE}

sed -i -e "62s/false/true/" ${CONF_FILE}

sed -i -e "82s/false/true/" ${CONF_FILE}

echo ""
