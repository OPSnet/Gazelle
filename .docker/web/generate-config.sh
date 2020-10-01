#!/usr/bin/env bash

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

TARGET=${THIS_DIR}/../../classes/config.php

if [ -f ${TARGET} ]; then
    exit 0;
fi

echo "GENERATING GAZELLE CONFIG..."
echo ""
sed -Ef $THIS_DIR/generate-config.sed \
    -e "s/('SQL(LOGIN|_PHINX_USER)', *')/\1${MYSQL_USER}/" \
    -e "s/('SQL(_PHINX_)?PASS', *')/\1${MYSQL_PASSWORD}/" \
    ${THIS_DIR}/../../classes/config.template.php > ${TARGET}

echo ""
