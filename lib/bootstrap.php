<?php

/* require this file to have a fully-initialized Gazelle runtime */

if (PHP_VERSION_ID < 80406) {
    $version = phpversion();
    die("Gazelle (Orpheus fork) requires at least PHP version 8.4.6, you have $version\n");
}
foreach (['memcached', 'mysqli'] as $e) {
    if (!extension_loaded($e)) {
        die("$e extension not loaded");
    }
}
error_reporting(E_ALL);
date_default_timezone_set('UTC');

if (!defined('SITE_NAME')) {
    ob_start();
    require __DIR__ . '/config.php';
    require __DIR__ . '/../lib/util.php';
    require __DIR__ . '/../vendor/autoload.php';
    ob_end_clean();
}

global $Cache, $Debug, $Twig;

$Cache = new Gazelle\Cache();
$Debug = new Gazelle\Debug($Cache, Gazelle\DB::DB());
$Twig  = Gazelle\Util\Twig::factory(new Gazelle\Manager\User());

Gazelle\Base::initialize($Cache, Gazelle\DB::DB(), $Twig);

Gazelle\Base::setRequestContext(new Gazelle\RequestContext(
    scriptName: 'none',
    remoteAddr: '127.0.0.1',
    useragent:  'none',
));
