<?php

$json = new Gazelle\Json\Torrent;

if (isset($_GET['id']) && isset($_GET['hash'])) {
    $json->failure('bad parameters');
    exit;
} elseif (isset($_GET['hash'])) {
    $torrent = (new Gazelle\Manager\Torrent)->findByInfohash($_GET['hash'] ?? '');
} else {
    $torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['id']);
}
if (is_null($torrent)) {
    $json->failure('bad parameters');
    exit;
}

$json->setVersion(5)
    ->setTorrent($torrent)
    ->setViewer($Viewer)
    ->emit();
