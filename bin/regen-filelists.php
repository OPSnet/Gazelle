<?php

namespace Gazelle;

require __DIR__ . '/../lib/bootstrap.php';

$Cache->disableLocalCache();

$torMan  = new Manager\Torrent();
$encoder = new \OrpheusNET\BencodeTorrent\BencodeTorrent();
$db      = DB::DB();
$max     = $db->scalar("SELECT max(ID) FROM torrents");
$id      = $argv[1] ?? 0;

while ($id < $max) {
    $id++;
    $db->prepared_query("
        SELECT ID
        FROM torrents
        WHERE ID >= ?
        ORDER BY ID
        LIMIT ?
    ", $id, 1000);
    $list = $db->collect(0);
    foreach ($list as $id) {
        try {
            $torMan->findById($id)->regenerateFilelist($encoder);
        } catch (\RuntimeException $e) {
            echo "$id: fail: " . $e->getMessage() . "\n";
        }
    }
}
