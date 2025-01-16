<?php

/* require this file to have a fully-initialized Gazelle runtime */

if (PHP_VERSION_ID < 80201) {
    die("Gazelle (Orpheus fork) requires at least PHP version 8.2.1");
}
foreach (['memcached', 'mysqli'] as $e) {
    if (!extension_loaded($e)) {
        die("$e extension not loaded");
    }
}
date_default_timezone_set('UTC');

if (!defined('SITE_NAME')) {
    include_once __DIR__ . '/config.php';
    include_once __DIR__ . '/../lib/util.php';
    include_once __DIR__ . '/../vendor/autoload.php';
}

global $Cache, $Debug, $Twig;

$Cache = new Gazelle\Cache();
$Debug = new Gazelle\Debug($Cache, Gazelle\DB::DB());
$Twig  = Gazelle\Util\Twig::factory(new Gazelle\Manager\User());

Gazelle\Base::initialize($Cache, Gazelle\DB::DB(), $Twig);

Gazelle\Base::setRequestContext(new Gazelle\BaseRequestContext(
    scriptName: 'none',
    remoteAddr: '127.0.0.1',
    useragent:  'none',
));
