<?php

$json = new Gazelle\Json\Torrent;

$torrentId = (int)$_GET['id'];
$torrentHash = $_GET['hash'];

if ($torrentId && $torrentHash) {
    $json->failure('bad parameters');
    exit;
} elseif ($torrentHash) {
    $torrent = (new Gazelle\Manager\Torrent)->findByInfohash($torrentHash);
} else {
    $torrent = (new Gazelle\Manager\Torrent)->findById($torrentId);
}
if (is_null($torrent)) {
    $json->failure('bad parameters');
    exit;
}

$json->setVersion(5)
    ->setTorrent($torrent)
    ->setViewer($Viewer)
    ->emit();
