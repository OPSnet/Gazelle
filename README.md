# Gazelle
Gazelle is a web framework geared towards private BitTorrent trackers. Although naturally focusing on music, it can be
modified for most needs. Gazelle is written in PHP, JavaScript, and MySQL.

## Gazelle Runtime Dependencies
* [Nginx](http://wiki.nginx.org/Main) (recommended)
* [PHP 7 or newer](https://www.php.net/) (required)
* [Memcached](http://memcached.org/) (required)
* [Sphinx 2.0.6 or newer](http://sphinxsearch.com/) (required)
* [procps-ng](http://sourceforge.net/projects/procps-ng/) (recommended)

## Gazelle/Ocelot Compile-time Dependencies
* [Git](http://git-scm.com/) (required)
* [GCC/G++](http://gcc.gnu.org/) (4.7+ required; 4.8.1+ recommended)
* [Boost](http://www.boost.org/) (1.55.0+ required)

_Note: This list may not be exhaustive._

## Installation
See the script in `.vagrant/gazelle-setup.sh` to get a gist of what needs to be done to install Gazelle on Debian
Jessie. You should be able to modify this to whatever distro you want to run it on.

## Logchecker
To fully utilize the Logchecker, you must install the following depedencies through pip:
* chardet
* eac-logchecker
* xld-logchecker

## Gazelle Development
Gazelle can be run through Docker (container) or Vagrant (Virtual Machine). Historically, Vagrant was used, but recently Docker support was added and is the preferred method of development.

### Docker (Recommended)
Install docker for your preferred system and start using the following command:

```
docker-compose up
```

This will build and pull the needed images. A volume is created between the source code and `/var/www`. This way, changes to the sourcecode are immediately served without rebuilding or restarting. 

### Vagrant (Legacy)
This repository comes pre-setup to be run through [Vagrant](https://www.vagrantup.com/) for ease of development and
without having to modify your local machine. You can look through the docs for how it works, but to start, you
just need to download Vagrant and VirtualBox (and it's recommended to get the
[vagrant-vbguest](https://github.com/dotless-de/vagrant-vbguest) plugin) and then simply run:
```
vagrant up
```

This will build a Debian Jessie on a Virtual Machine and serve this repository through `/var/www` on the machine.

### Forwarded Ports
Both Docker and Vagrant will forward the following ports:
* 8080 -> 80 (nginx)
* 36000 -> 3306 (mysql)
* 34000 -> 34000 (ocelot)

You can access the site by going to `http://localhost:8080`

## Contact and Discussion
Feel free to join #develop on irc.orpheus.network to discuss any questions concerning Gazelle (or any of the repos used by
Orpheus).
