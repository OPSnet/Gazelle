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
This repository does not come with the necessary binaries to validate checksums for uploaded logs. To get them, please
follow the below steps. In all cases, you will need to place the necessary files into the `classes/logchecker/` folder.

### EAC
Install a copy of [EAC](http://www.exactaudiocopy.de/) on a Windows machine or under Wine. You then need to navigate
to the installed directory and copy `CheckLog.exe` (renaming it to `eac_logchecker.exe`) and `HelperFunctions.dll` into
`classes/logchecker/`.

### XLD
Clone the repository https://github.com/itismadness/xld_sign and build it following the readme. Move the generated
binary (renaming it to `xld_logchecker`) to `classes/logchecker`.

## Gazelle Development
This repository comes pre-setup to be run through [Vagrant](https://www.vagrantup.com/) for ease of development and 
without having to modify your local machine. You can look through the docs for how it works, but to start, you
just need to download Vagrant and VirtualBox (and it's recommended to get the 
[vagrant-vbguest](https://github.com/dotless-de/vagrant-vbguest) plugin) and then simply run:
```
vagrant up
```

This will build a Debian Jessie on a Virtual Machine and serve this repository through `/var/www` on the machine. It
will also forward the following ports:
* 8080 -> 80 (nginx)
* 36000 -> 3306 (mysql)
* 34000 -> 34000 (ocelot)

You can access the site by going to `http://localhost:8080`

Feel free to join #develop on irc.apollo.rip to discuss any questions concerning Gazelle (or any of the repos used by
Apollo).