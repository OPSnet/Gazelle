.DEFAULT_GOAL := help

NOW := $(shell date +'%Y%m%d-%H%M%S')
STORAGE_PATH_RIPLOG     := $(shell bin/getconf STORAGE_PATH_RIPLOG)
STORAGE_PATH_RIPLOGHTML := $(shell bin/getconf STORAGE_PATH_RIPLOGHTML)
STORAGE_PATH_TORRENT    := $(shell bin/getconf STORAGE_PATH_TORRENT)

.SILENT: help
.PHONY: help
help:
	echo '  help                 - output this message'
	echo '  build-css            - build the CSS'
	echo '  check-php            - check that the modified PHP files are syntactically correct'
	echo '  composer-dev-update  - run local composer update'
	echo '  composer-live-update - run production composer install from composer.lock'
	echo '  dump-all             - create tarballs of the following:'
	echo '  dump-riplog          - create a tarball of the rip logs'
	echo '  dump-riploghtml      - create a tarball of the HTMLified rip logs'
	echo '  dump-torrent         - create a tarball of the rip logs'
	echo '  git-submodules       - update the git submodules'
	echo '  lint-css             - lint (style check) the CSS'
	echo '  lint-js              - lint (style check) the Javascript'
	echo '  lint-php             - lint (style check) the PHP'
	echo '  lint-twig            - lint (style check) the Twig templates'
	echo '  lint-staged          - lint (style check) all staged changes'
	echo '  reformat-staged      - reformat all staged changes'
	echo '  mysqldump            - dump mysql database from docker to misc/mysql-dump.sql'
	echo '  ocelot-reload-conf   - signal Ocelot to reload its configuration'
	echo '  ocelot-reload-db     - signal Ocelot to reload from database'
	echo '  pgdump               - dump postgresql database from docker to misc/postgresql-dump.sql'
	echo '  phpstan-analyse      - run phpstan over the code'
	echo '  phpstan-baseline     - generate a new phpstan baseline'
	echo '  test                 - run unit test suite'
	echo '  twig-flush           - purge the Twig cache'

.PHONY: build-css
build-css:
	docker compose exec -T web npm run build:scss

.PHONY: check-php
check-php:
	git status | awk '/(modified|new file):.*\.php$$/ {print $$NF}' | xargs php -l

.PHONY: composer-dev-update
composer-dev-update:
	composer update --optimize-autoloader

.PHONY: composer-live-update
composer-live-update:
	composer install --no-dev --optimize-autoloader --no-progress

.PHONY: dump-all
dump-all: dump-riplog dump-riploghtml dump-torrent

.PHONY: dump-riplog
dump-riplog:
	tar -C "$(STORAGE_PATH_RIPLOG)/.." -jcf riplog.$(NOW).tar.bz2 "$$(basename $(STORAGE_PATH_RIPLOG))"

.PHONY: dump-riploghtml
dump-riploghtml:
	tar -C "$(STORAGE_PATH_RIPLOGHTML)/.." -jcf riploghtml.$(NOW).tar.bz2 "$$(basename $(STORAGE_PATH_RIPLOGHTML))"

.PHONY: dump-torrent
dump-torrent:
	tar -C "$(STORAGE_PATH_TORRENT)/.." -jcf torrent.$(NOW).tar.bz2 "$$(basename $(STORAGE_PATH_TORRENT))"

.PHONY: git-submodules
git-submodules:
	git pull --recurse-submodules

.PHONY: lint-css
lint-css:
	docker compose exec -T web node_modules/.bin/stylelint --config misc/stylelint.json --cache --cache-location cache/stylelint 'sass/**/*.scss'
	docker compose exec -T web node_modules/.bin/stylelint --config misc/stylelint.json --cache --cache-location cache/stylelint 'sass/**/*.scss' --custom-formatter ./node_modules/stylelint-checkstyle-formatter/index.js

.PHONY: lint-js
lint-js:
	npx eslint -c misc/eslint.config.mjs public/static

.PHONY: lint-php
lint-php:
	find . -path ./vendor -prune \
        -o -path ./cache -prune \
        -o -path ./node_modules -prune \
        -o -path ./misc/docker -prune \
        -o -type f -name '*.php' \
        print0 \
        | xargs -0 php -l -n | grep -v '^No syntax errors detected in' || true
	vendor/bin/phpcs -p --report-width=256
	vendor/bin/phpstan analyse --memory-limit=1024M --configuration=misc/phpstan.neon

.PHONY: lint-twig
lint-twig:
	bin/twig-parse $(find templates -type f)

.PHONY: lint-staged
lint-staged:
	bin/lint-staged

.PHONY: reformat-staged
reformat-staged:
	bin/reformat-staged

# defaults file must be chmod 0400 and look something like:
#
# [mysqldump]
# user=gazelle
# password=password

.PHONY: mysqldump
mysqldump:
	docker compose exec -T mysql mysqldump --defaults-file=~/mysqldump.cnf gazelle --single-transaction > misc/mysql-dump.sql

.PHONY: ocelot-reload-conf
ocelot-reload-conf:
	pkill -HUP ocelot

.PHONY: ocelot-reload-db
ocelot-reload-db:
	pkill -USR1 ocelot

.PHONY: pgdump
pgdump:
	docker exec -e POSTGRES_PASSWORD=nyalapw $(shell docker ps|awk '/postgres:/ {print $$1}') pg_dumpall -U nyala > misc/postgresql-dump.sql

.PHONY: phpstan-analyse
phpstan-analyse:
	vendor/bin/phpstan analyse --memory-limit=1024M --configuration=misc/phpstan.neon

.PHONY: phpstan-baseline
phpstan-baseline:
	vendor/bin/phpstan analyse --memory-limit=1024M --configuration=misc/phpstan.neon --generate-baseline misc/phpstan-baseline.neon

.PHONY: rector
rector:
	vendor/bin/rector process --config misc/rector.php

.PHONY: rector-dry-run
rector-dry-run:
	vendor/bin/rector process --dry-run --config misc/rector.php

.PHONY: test
test:
	docker compose exec -T web vendor/bin/phpunit -c misc/phpunit.xml

.PHONY: twig-flush
twig-flush:
	find cache/twig -mindepth 1 -depth -delete
