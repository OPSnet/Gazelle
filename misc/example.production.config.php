<?php

// Copy these variables to your override.config.php in a production environment.
// Change as required.

define('SITE_NAME', 'Gazelle');
define('SITE_HOST', 'localhost');
define('SITE_LAUNCH_YEAR', 2018);

define('SOURCE', 'GZL');

define('TMPDIR', '/tmp');

define('STORAGE_PATH_TORRENT',    '/var/lib/gazelle/torrent');
define('STORAGE_PATH_RIPLOG',     '/var/lib/gazelle/riplog');
define('STORAGE_PATH_RIPLOGHTML', '/var/lib/gazelle/riploghtml');

define('TRACKER_HOST', '10.0.0.2');
define('TRACKER_NAME', 'tracker.example.com');
define('TRACKER_PORT', 12345);

define('SQLHOST', '10.0.0.1');
define('IRC_HOSTNAME', 'irc.example.com');

// If your database is on the same host as the PHP interpreter
define('SQLSOCK', '/var/run/mysqld/mysqld.sock');

define('SQLDB',    'changeme');
define('SQLLOGIN', 'changeme');
define('SQLPASS',  'changeme');

define('SQL_PHINX_USER', 'thisis.phinx');
define('SQL_PHINX_PASS', 'thisis.phinx');

define('SPHINX_HOST', 'sphinx.example.com');
define('SPHINXQL_HOST', SPHINX_HOST);

// If sphinx is running on the same host as the PHP interpreter
define('SPHINXQL_SOCK', '/var/run/sphinx.sock');

define('TRACKER_SECRET',    '0123456789abcdef0123456789abcdef01');
define('TRACKER_REPORTKEY', '0123456789abcdef0123456789abcdef01');

define('ENCKEY',       'changeme');
define('AUTHKEY',      'changeme');
define('RSS_HASH',     'changeme');
define('SEEDBOX_SALT', 'changeme');
define('AVATAR_SALT',  'changeme');

define('PARANOIA_PREVIEW_USER', 1);

define('IMAGE_HOST_BANNED', ['badhost.example.com']);
define('IMAGE_HOST_RECOMMENDED', ['goodhost.example.com']);
