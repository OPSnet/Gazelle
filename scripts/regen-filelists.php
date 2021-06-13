<?php

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../classes/util.php');

$Cache = new Gazelle\Cache;
$InternalCache = false;

$DB    = new DB_MYSQL;
Gazelle\Base::initialize($Cache, $DB, Gazelle\Util\Twig::factory());
$Debug = new Gazelle\Debug($Cache, $DB);

$torMan = new Gazelle\Manager\Torrent;
$max = $DB->scalar("SELECT MAX(ID) FROM torrents");
$id = 0;

while ($id < $max) {
    $id++;
    $DB->prepared_query("
        SELECT ID
        FROM torrents
        WHERE ID >= ?
        ORDER BY ID
        LIMIT ?
    ", $id, 1000);
    $list = $DB->collect(0);
    foreach ($list as $id) {
        try {
            $torMan->regenerateFilelist($id);
        } catch (RuntimeException $e) {
            echo "$id: fail: " . $e->getMessage() . "\n";
        }
    }
}
