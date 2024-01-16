# Gazelle

Gazelle is a web framework geared towards private BitTorrent trackers.
Although naturally focusing on music, it can be modified for most
needs. Gazelle is written in PHP, Twig and JavaScript. It traditionally
uses Mysql and Sphinx, but work is under way to replace those with
Postgresql.

## Gazelle Runtime Dependencies
* [PHP 8.2.13+](https://www.php.net/) (required)
* [nginx](http://wiki.nginx.org/Main) (required)
* [nodeJS 20+](https://nodejs.org/en/) (required)
* [memcached](http://memcached.org/) (required)
* [sphinx 2.1.1 or newer](http://sphinxsearch.com/) (required)
* [ocelot](https://github.com/OPSnet/Ocelot) (optional)

## Logchecker
To fully utilize the Logchecker, you must install the following
depedencies through `pip`:
* chardet
* eac-logchecker
* xld-logchecker

## Installation

We provide installation notes [here](docs/INSTALL.txt). These notes are provided
as a best effort, and are not guaranteed to be fully up-to-date or accurate.

Due to the nature of torrenting, we HIGHLY recommend not trying to run this in
production if you are not prepared or knowledgeable in setting up servers,
proxies, and tuning TCP configs to obtain proper performance and privacy.

## Development
Docker is used to develop Gazelle. See https://docs.docker.com/engine/install/
for more information on getting Docker set up locally.

### Gazelle
In the root folder of the Gazelle repository, run the following command:

`docker compose up -d`

This will pull and build the needed images to run Gazelle on Debian
Bullseye. A volume is mounted from the base of the git repository at
`/var/www` in the container. Changes to the source code are
immediately served without rebuilding or restarting.

If you receive the error "http: invalid Host header", run the following

`DOCKER_BUILDKIT=0 docker compose up -d`

You can access the site by viewing `http://localhost:7001/`

Follow the "Register" link on the homepage to create the first account.
The first account created is assigned the highest operational role
(Sysop) automatically.

The Sysop account might not have all the permissions that have
been added recently. Navigate to the /tools.php?action=userclass
page and tick everything for the Sysop class.

### Ocelot
The [ocelot](https://github.com/OPSnet/Ocelot) repository is used to build the
Ocelot image. To keep things simple, check out the source in a sibling
directory to Gazelle.

```bash
$ git clone https://github.com/OPSnet/ocelot
$ cd ocelot
$ docker build . -t ocelot
```

Ocelot can be launched by specifying an additional configuration file:

`docker compose -f docker-compose.yml -f misc/docker-compose.ocelot.yml up -d`

### Ports
The following ports are forwarded:
* 80 -> 7001 (web)
* 3306 -> 36000 (mysql)
* 34000 -> 34000 (ocelot if present)

## Going further
If you want to poke around inside the web container, open a shell:

`export WEBCONT=$(docker ps|awk '$2 ~ /web$/ {print $1}')`

`docker exec -it $WEBCONT bash`

To keep an eye on PHP errors during development:

`docker exec -it $WEBCONT tail -n 20 -f /var/log/nginx/error.log`

To create a Phinx migration:

`docker exec -it $WEBCONT vendor/bin/phinx create MyNewMigration`

Edit the resulting file and then apply it:

`docker exec -it $WEBCONT vendor/bin/phinx migrate`

For postgres add `-c ./misc/phinx-pg.php` to the END of the phinx commands

To access the database, look at `.docker/mysql-home/.my.cnf`
The credentials should match those used in the `docker-compose.yml` file.

And then:

`docker exec -it $MYSQLCONT mysql`

In the same vein, you can use `mysqldump` to perform a backup.

To view the sphinx tables:

`export SPHINXCONT=$(docker ps|awk '$2 ~ /sphinxsearch/ {print $1}')`
`docker exec -it $SPHINXCONT  mysql -h 127.0.0.1 -P 9306`

#### Boris
You can run Boris directly:

`docker exec -it $WEBCONT /var/www/boris`

#### Gitlab CI testrunner
`.docker/gitlab.Dockerfile` can be used to create a Docker container suitable for
running the test suite in a Gitlab CI runner. The included `.gitlab-ci.yml`
config runs unit tests with phpunit and afterwards end-to-end tests with
cypress.

To build the container, get the sql files from the OPSnet/gazelle-e2e-testing-docker
repo and place them alongside the `gitlab.Dockerfile`. Then run

    docker build -f .docker/gitlab.Dockerfile -t gazelle-e2e-testing:latest --compress .

in the gazelle repo's root directory (this one).

Similarly, the phpstan container can be built with

    docker build -t gazelle-phpstan:latest -f .docker/Dockerfile-phpstan --compress .docker


## Upgrading Postgresql
Major Pg versions require a dump and restore. In the docker environment
this means doing the following;
 - run 'make pgdump' to dump the current contents
 - stop the current postgresql container
 - mv .docker/data/pg .docker/data/pg.old
 - mkdir ./docker/data/pg
 - docker-compose stop && docker-compose up -d
 - import the dump to the docker pg container

## Contact and Discussion
Feel free to join #develop on irc.orpheus.network to discuss any
questions concerning Gazelle (or any of the repos published by
Orpheus).

## Open source
Create issues at https://github.com/OPSnet
Patches welcome!
