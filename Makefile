.DEFAULT_GOAL := help

NOW := $(shell date +'%Y%m%d-%H%M%S')
STORAGE_PATH_RIPLOG     := $(shell scripts/getconf STORAGE_PATH_RIPLOG)
STORAGE_PATH_RIPLOGHTML := $(shell scripts/getconf STORAGE_PATH_RIPLOGHTML)
STORAGE_PATH_TORRENT    := $(shell scripts/getconf STORAGE_PATH_TORRENT)

.SILENT: help
.PHONY: help
help:
	echo '  help               - output this message'
	echo '  build-css          - build the CSS'
	echo '  dump-all           - create tarballs of the following:'
	echo '  dump-riplog        - create a tarball of the rip logs'
	echo '  dump-riploghtml    - create a tarball of the HTMLified rip logs'
	echo '  dump-torrent       - create a tarball of the rip logs'
	echo '  lint-css           - lint (style check) the CSS'
	echo '  mysqldump          - dump mysql database from docker to db/data/gazelle.sql'
	echo '  ocelot-reload-conf - signal Ocelot to reload its configuration'
	echo '  ocelot-reload-db   - signal Ocelot to reload from database'
	echo '  test               - run all linters and unit test suite'
	echo '  twig-flush         - purge the Twig cache'
	echo '  update             - pull from git and run production composer install'

.PHONY: build-css
build-css:
	yarn build:scss

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

.PHONY: lint-css
lint-css:
	yarn lint:css
	yarn lint:css-checkstyle

.PHONY: lint-php
lint-php:
	yarn lint:php:internal
	yarn lint:php:phpcs || exit 0
	composer phpstan

.PHONY: mysqldump
mysqldump:
	mysqldump -h 127.0.0.1 -P 36000 -u gazelle --password=password -d gazelle --skip-add-drop-table --skip-add-locks --single-transaction | sed 's/ AUTO_INCREMENT=[0-9]*//g' > db/data/gazelle.sql

.PHONY: ocelot-reload-conf
ocelot-reload-conf:
	pkill -HUP ocelot

.PHONY: ocelot-reload-db
ocelot-reload-db:
	pkill -USR1 ocelot

.PHONY: test
test: lint-css lint-php
	composer test

.PHONY: twig-flush
twig-flush:
	find cache/twig -mindepth 1 -depth -delete

.PHONY: update
update:
	git pull
	composer install --no-dev --optimize-autoloader --no-progress
