<?php

// Copy these variables to your override.config.php in a development environment.
// Change as required.

define('SITE_URL', 'http://localhost:7001');

define('DISABLE_TRACKER', true);
define('DISABLE_IRC', true);

define('DEBUG_EMAIL', true);
define('DEBUG_TWIG', true);
define('DEBUG_UPLOAD_NOTIFICATION', true);

define('OPEN_REGISTRATION', true);
define('SECURE_COOKIE', false);

define('MEMCACHE_HOST_LIST', [['host' => 'memcached', 'port' => 11211, 'buckets' => 1]]);

define('ENCKEY',       "");
define('AUTHKEY',      "");
define('RSS_HASH',     "");

define('SEEDBOX_SALT', "");
define('AVATAR_SALT',  "");

define('MYSQL_PHINX_USER', 'root');
define('MYSQL_PHINX_PASS', 'sc5tlc9JSCC6');

// Docker setup runs the scheduler only once every 15 minutes
define('SCHEDULER_DELAY', 1200);

define('PASSWORD_HASH_ALGO', PASSWORD_ARGON2ID);
// fast, insecure hashing for development, do not use this in production!
define('PASSWORD_HASH_OPTS', ["memory_cost" => 8, "time_cost" => 1]);
define('PASSWORD_PEPPER', '');
define('LOGIN_SLEEP_USEC', 0);

define('SOURCE_FLAG', 'OPS');

define('IMAGE_HOST_BANNED', ['phpunit.bad']);
