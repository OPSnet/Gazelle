<?php

/* require this file to have a fully-initialized Gazelle runtime */

$now = microtime(true); // To track how long a page takes to create

if (!defined('SITE_NAME')) {
    require_once(__DIR__ . '/config.php');
    require_once(__DIR__ . '/../lib/util.php');
    require_once(__DIR__ . '/../vendor/autoload.php');
}

$Cache = new Gazelle\Cache;
$DB    = new Gazelle\DB\Mysql(SQLDB, SQLLOGIN, SQLPASS, SQLHOST, SQLPORT, SQLSOCK);
$Debug = new Gazelle\Debug($Cache, $DB);
$Debug->setStartTime($now)
    ->handle_errors()
    ->set_flag('init');

$Twig = Gazelle\Util\Twig::factory();
Gazelle\Base::initialize($Cache, $DB, $Twig);
