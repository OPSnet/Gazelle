<?php

if (isset($_GET['id']) && isset($_GET['hash'])) {
    json_error('bad parameters');
} elseif (isset($_GET['hash'])) {
    $torrent = (new Gazelle\Manager\Torrent)->findByInfohash($_GET['hash'] ?? '');
} else {
    $torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['id']);
}
if (is_null($torrent)) {
    json_error('bad parameters');
}

echo (new Gazelle\Json\Torrent($torrent, $Viewer, new Gazelle\Manager\Torrent))
    ->setVersion(5)
    ->response();
