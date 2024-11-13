#!/bin/bash

set -euo pipefail

npm_config_cache="${CI_PROJECT_DIR}/node_modules/.npm-cache"
export npm_config_cache

npm install
npx browserslist@latest --update-db
npm run dev
