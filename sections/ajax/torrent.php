<?php

$json = new Gazelle\Json\Torrent;

$torrentId = (int)$_GET['id'];
$torrentHash = $_GET['hash'];

if ($torrentId && $torrentHash) {
    $json->failure('bad parameters');
    exit;
} elseif ($torrentHash) {
    if (!$json->findByInfohash($torrentHash)) {
        exit;
    }
} else {
    if (!$json->findById($torrentId)) {
        exit;
    }
}

$json->setVersion(5)
    ->setViewerId($Viewer->id())
    ->emit();
