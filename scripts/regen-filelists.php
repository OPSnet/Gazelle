<?php

require_once(__DIR__ . '/../lib/bootstrap.php');

$torMan = new Gazelle\Manager\Torrent;
$max = $DB->scalar("SELECT max(ID) FROM torrents");
$id = $argv[1] ?? 0;

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
