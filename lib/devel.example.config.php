<?php

// Copy these variables to your config.override.php in a development environment.
// Change as required.

define('SITE_URL', 'http://localhost:8080');

define('DISABLE_TRACKER', true);
define('DISABLE_IRC', true);

define('DEBUG_EMAIL', true);
define('DEBUG_MODE', true);
define('DEBUG_WARNINGS', true);
define('DEBUG_UPLOAD_NOTIFICATION', true);

define('OPEN_REGISTRATION', true);

define('MEMCACHE_HOST_LIST', [['host' => 'memcached', 'port' => 11211, 'buckets' => 1]]);

define('ENCKEY',       "");
define('SCHEDULE_KEY', "");
define('RSS_HASH',     "");

define('SEEDBOX_SALT', "");
define('AVATAR_SALT',  "");

// Docker setup runs the scheduler only once every 15 minutes
define('SCHEDULER_DELAY', 1200);
