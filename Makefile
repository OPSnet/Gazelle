.DEFAULT_GOAL := help

.SILENT: help
.PHONY: help
help:
	echo '  help               - output this message'
	echo '  build-css          - build the CSS'
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

.PHONY: lint-css
lint-css:
	yarn lint:css
	yarn lint:css-checkstyle

.PHONY: mysqldump
mysqldump:
	mysqldump -h 127.0.0.1 -P 36000 -u gazelle --password=password -d gazelle --skip-add-drop-table --skip-add-locks --single-transaction | sed 's/ AUTO_INCREMENT=[0-9]*//g' > db/data/gazelle.sql

.PHONY: ocelot-reload-conf
ocelot-reload-conf:
	pkill -HUP ocelot

.PHONY: ocelot-reload-db
ocelot-reload-reload:
	pkill -USR1 ocelot

.PHONY: test
test: lint-css
	yarn lint:php:internal
	yarn lint:php:phpcs || exit 0
	composer phpstan
	composer test

.PHONY: twig-flush
twig-flush:
	find cache/twig -mindepth 1 -depth -delete

.PHONY: update
update:
	git pull
	composer install --no-dev --optimize-autoloader --no-suggest --no-progress
