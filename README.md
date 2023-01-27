# Gazelle

Gazelle is a web framework geared towards private BitTorrent trackers.
Although naturally focusing on music, it can be modified for most
needs. Gazelle is written in PHP, Twig, JavaScript, and MySQL.

## Gazelle Runtime Dependencies
* [Nginx](http://wiki.nginx.org/Main) (recommended)
* [PHP 8.2.1+](https://www.php.net/) (required)
* [NodeJS 12+](https://nodejs.org/en/) (required)
* [Memcached](http://memcached.org/) (required)
* [Sphinx 2.0.6 or newer](http://sphinxsearch.com/) (required)
* [ocelot](https://github.com/OPSnet/Ocelot) (required)
* [procps-ng](http://sourceforge.net/projects/procps-ng/) (recommended)

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
proxies, and tuning TCP configs to get proper performance and privacy.

## Development
Docker is used to develop Gazelle. See https://docs.docker.com/engine/install/
for more information on getting Docker set up locally.

### Ocelot
The [ocelot](https://github.com/OPSnet/Ocelot) repository is used to build the Ocelot image. To keep things simple, check out the source in a
sibling directory to Gazelle.

```bash
$ git clone https://github.com/OPSnet/ocelot
$ cd ocelot
$ docker build . -t ocelot
```

### Gazelle
In the root folder of the Gazelle repository, run the following command:

`docker-compose up`

This will pull and build the needed images to run Gazelle on Debian
Bullseye. A volume is mounted from the base of the git repository at
`/var/www` in the container. Changes to the source code are
immediately served without rebuilding or restarting.

You can access the site by viewing `http://localhost:8080`

The first account is 'admin' and has the highest level of  access
to the site installation. The second account is 'user' and has
standard user access. The passwords for both accounts are literally
'password' (without the quotes). If you want to change these before
building, edit db/seeds/InitialUserSeeder.php first.

The 'admin' account might not have all the permissions that have
been added recently. Navigate to the /tools.php?action=permissions
page and tick everything.

### Ports
The following ports are forwarded:
* 80 -> 7001 (web)
* 3306 -> 36000 (mysql)
* 34000 -> 34000 (ocelot)

## Going further
You may want to install additional packages:
* `apt update`
* `apt install less procps vim`

If you want to poke around inside the web container, open a shell:

`export WEBCONT=$(docker ps|awk '$2 ~ /web$/ {print $1}')`

`docker exec -it $WEBCONT bash`

To keep an eye on PHP errors during development:

`docker exec -it $WEBCONT tail -n 20 -f /var/log/nginx/error.log`

To create a Phinx migration:

`docker exec -it $WEBCONT vendor/bin/phinx create MyNewMigration`

Edit the resulting file and then apply it:

`docker exec -it $WEBCONT vendor/bin/phinx migrate`

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

#### Production Mode (not yet fully baked)
In order to have Docker run the container using the production mode commands
for both Composer and NPM, run this when powering it up:

`ENV=prod docker-compose up`

#### Gitlab CI testrunner
`Dockerfile-gitlab` can be used to create a Docker container suitable for
running the test suite in a Gitlab CI runner. The included `.gitlab-ci.yml`
config runs unit tests with phpunit and afterwards end-to-end tests with
cypress.

To build the container, get the sql files from the OPSnet/gazelle-e2e-testing-docker
repo and place them alongside the `Dockerfile-gitlab`. Then run

    docker build -f Dockerfile-gitlab -t gazelle-e2e-testing:latest --compress .

in the gazelle repo's root directory (this one).

## Contact and Discussion
Feel free to join #develop on irc.orpheus.network to discuss any
questions concerning Gazelle (or any of the repos published by
Orpheus).

## Open source
Create issues at https://github.com/OPSnet
Patches welcome!
