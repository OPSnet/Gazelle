# Gazelle
Gazelle is a web framework geared towards private BitTorrent trackers.
Although naturally focusing on music, it can be modified for most
needs. Gazelle is written in PHP, JavaScript, and MySQL.

## Gazelle Runtime Dependencies
* [Nginx](http://wiki.nginx.org/Main) (recommended)
* [PHP 7 or newer](https://www.php.net/) (required)
* [Memcached](http://memcached.org/) (required)
* [Sphinx 2.0.6 or newer](http://sphinxsearch.com/) (required)
* [procps-ng](http://sourceforge.net/projects/procps-ng/) (recommended)
* ocelot

_Note: This list may not be exhaustive._

## Logchecker
To fully utilize the Logchecker, you must install the following
depedencies through `pip`:
* chardet
* eac-logchecker
* xld-logchecker

## Gazelle Development
Docker is used to develop Gazelle. See https://docs.docker.com/engine/install/
for more information on getting Docker set up locally.

Setup the ocelot container, by cloning it and running:

```bash
docker build . -t ocelot
```

Within the gazelle folder, run the following command:


```bash
docker-compose up
```

This will build and pull the needed images to run Gazelle on Debian
Buster. A volume is mounted from the base of the git repository at
`/var/www` in the container. Changes to the source code are
immediately served without rebuilding or restarting.

### Ports
The following ports are forwarded:
* 80 -> 8080 (web)
* 3306 -> 36000 (mysql)
* 34000 -> 34000 (ocelot)

You can access the site by going to `http://localhost:8080`

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

To access the database, save the following in `~root/.my.cnf` of
the database container:

```
    [mysql]
    user = root
    password = <sekret>
    database = gazelle
```

And then:

`docker exec -it $(docker ps|awk '$2 ~ /^mariadb/ {print $1}') mysql`

In the same vein, you can use `mysqldump` to perform a backup.

#### Boris
You can run Boris directly:

`docker exec -it $WEBCONT /var/www/boris`

#### Production Mode (not fully baked yet)
In order to have Docker run the container using the production mode commands
for both Composer and NPM, run this when powering it up:

`ENV=prod docker-compose up`

## Contact and Discussion
Feel free to join #develop on irc.orpheus.network to discuss any
questions concerning Gazelle (or any of the repos published by
Orpheus).

## Open source
Open issues at https://github.com/OPSnet.
Patches welcome!
