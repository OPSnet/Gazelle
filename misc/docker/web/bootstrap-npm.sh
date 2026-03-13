#!/bin/bash

set -euo pipefail

npm_config_cache="${CI_PROJECT_DIR}/node_modules/.npm-cache"
export npm_config_cache

bin/config-css /tmp/config-css.js

npm install -g npm@11.11.1
npm install sass
npx update-browserslist-db@latest
npx puppeteer browsers install chrome
npm run build
