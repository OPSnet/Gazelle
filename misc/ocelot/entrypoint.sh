#!/bin/bash

set -euo pipefail

while ! nc -z web 80
do
    echo "Waiting for web..."
    sleep 10
done

exec /srv/ocelot
