#!/bin/bash

counter=1
while ! curl --fail http://web > /dev/null 2>&1; do
    sleep 1
    counter=$((counter + 1))
    if [ $((counter % 20)) -eq 0 ]; then
        >&2 echo "Still waiting for Web (Count: $counter)."
    fi
done

exec /srv/ocelot
