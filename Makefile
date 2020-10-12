.DEFAULT_GOAL := help

.SILENT: help
.PHONY: help
help:
	echo 'Gazelle Make'
	echo
	echo '  Usage:'
	echo '    make <target>'
	echo
	echo '  Targets:'
	echo '    help        - output this message'
	echo '    update      - pull from git and run production composer install'
	echo '    mysqldump   - dump mysql database from docker to db/data/gazelle.sql'
	echo '    test        - run all linters and unit test suite'

.PHONY: update
update:
	git pull
	composer install --no-dev --optimize-autoloader --no-suggest --no-progress

.PHONY: mysqldump
mysqldump:
	mysqldump -h 127.0.0.1 -P 36000 -u gazelle --password=password -d gazelle --skip-add-drop-table --skip-add-locks --single-transaction | sed 's/ AUTO_INCREMENT=[0-9]*//g' > db/data/gazelle.sql

.PHONY: test
test:
	yarn lint:css
	yarn lint:css-checkstyle
	yarn lint:php:internal
	yarn lint:php:phpcs || exit 0
	composer phpstan
	composer test
