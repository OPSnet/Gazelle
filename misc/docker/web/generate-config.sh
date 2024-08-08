#!/bin/bash

set -euo pipefail

MISC_DIR="$(dirname "$0")/../.."
SOURCE="${MISC_DIR}/example.local.config.php"
TARGET="${MISC_DIR}/../lib/override.config.php"

[ -f ${TARGET} ] && exit 0
echo "Generating configuration parameters..."
(
    perl -ple 's/""/q{"} . qx(head \/dev\/urandom|tr -dc 0-9A-Za-z|head -c 16) . q{"}/e' "${SOURCE}"
    date +"define('SITE_LAUNCH_YEAR', %Y);"
    date +"define('TOP_TEN_HISTORY_BEGIN', '%Y-%m-%d');"
) > "${TARGET}"
