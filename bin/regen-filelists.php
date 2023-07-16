<?php

require_once(__DIR__ . '/../lib/bootstrap.php');
$Cache->disableLocalCache();

$torMan  = new Gazelle\Manager\Torrent;
$filer   = new Gazelle\File\Torrent;
$encoder = new OrpheusNET\BencodeTorrent\BencodeTorrent;
$db      = Gazelle\DB::DB();
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
    $list = $db->collect(0, false);
    foreach ($list as $id) {
        try {
            $torMan->findById($id)->regenerateFilelist($filer, $encoder);
        } catch (RuntimeException $e) {
            echo "$id: fail: " . $e->getMessage() . "\n";
        }
    }
}
