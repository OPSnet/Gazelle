<?php

$json = new Gazelle\Json\Torrent;

$torrentId = (int)$_GET['id'];
$torrentHash = $_GET['hash'];

if ($torrentId && $torrentHash) {
    $json->failure('bad parameters');
    exit;
} elseif ($torrentHash) {
    if (!$json->setIdFromHash($torrentHash)) {
        exit;
    }
} else {
    if (!$json->setId($torrentId)) {
        exit;
    }
}

$json->setVersion(1)
    ->setViewer($LoggedUser['ID'])
    ->emit();
