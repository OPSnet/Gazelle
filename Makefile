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
	echo '  lint-php             - lint (style check) the PHP'
	echo '  lint-twig            - lint (style check) the Twig templates'
	echo '  mysqldump            - dump mysql database from docker to misc/mysql-dump.sql'
	echo '  ocelot-reload-conf   - signal Ocelot to reload its configuration'
	echo '  ocelot-reload-db     - signal Ocelot to reload from database'
	echo '  pgdump               - dump postgresql database from docker to misc/postgresql-dump.sql'
	echo '  phpstan-analyse      - run phpstan over the code'
	echo '  phpstan-baseline     - generate a new phpstan baseline'
	echo '  test                 - run all linters and unit test suite'
	echo '  twig-flush           - purge the Twig cache'

.PHONY: build-css
build-css:
	yarn build:scss

.PHONY: check-php
check-php:
	git status | awk '/(modified|new file):/ {print $$NF}' | xargs -n1 php -l

.PHONY: composer-dev-update
composer-dev-update:
	composer update --optimize-autoloader

.PHONY: composer-prod-update
composer-prod-update:
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
	yarn lint:css
	yarn lint:css-checkstyle

.PHONY: lint-php
lint-php:
	yarn lint:php:internal
	yarn lint:php:phpcs || exit 0
	vendor/bin/phpstan analyse --memory-limit=1024M --configuration=misc/phpstan.neon

.PHONY: lint-twig
lint-twig:
	bin/twig-parse $(find templates -type f)

# defaults file must be chmod 0400 and look something like:
#
# [mysqldump]
# user=gazelle
# password=password

.PHONY: mysqldump
mysqldump:
	docker exec $(shell docker ps|awk '/percona:/ {print $$1}') mysqldump --defaults-file=~/mysqldump.cnf gazelle --single-transaction > misc/mysql-dump.sql

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
test: lint-css lint-php lint-twig
	composer test

.PHONY: twig-flush
twig-flush:
	find cache/twig -mindepth 1 -depth -delete
