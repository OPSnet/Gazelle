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

$now = microtime(true); // To track how long a page takes to create

if (!defined('SITE_NAME')) {
    require_once(__DIR__ . '/config.php');
    require_once(__DIR__ . '/../lib/util.php');
    require_once(__DIR__ . '/../vendor/autoload.php');
}

global $Cache, $Debug, $Twig;

$Cache = new Gazelle\Cache();
$Twig  = Gazelle\Util\Twig::factory();
Gazelle\Base::initialize($Cache, Gazelle\DB::DB(), $Twig);

$Debug = new Gazelle\Debug($Cache, Gazelle\DB::DB());
$Debug->setStartTime($now)
    ->handle_errors()
    ->set_flag('init');
