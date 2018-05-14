#!/usr/bin/env bash
# run this file from the base project directory

sed -f static/styles/apollostage_sunset/create_me.sed static/styles/apollostage/style.css > static/styles/apollostage_sunset/style.css
sed -f static/styles/apollostage_coffee/create_me.sed static/styles/apollostage/style.css > static/styles/apollostage_coffee/style.css
