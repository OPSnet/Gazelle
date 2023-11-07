<?php

$groupId  = (int)($_GET['id'] ?? 0);
$infohash = $_GET['hash'] ?? null;
if ($groupId && $infohash) {
    json_error('bad parameters');
}

$tgMan = new Gazelle\Manager\TGroup;
$tgroup = $infohash
    ? $tgMan->findByTorrentInfohash($infohash)
    : $tgMan->findById($groupId);

if (is_null($tgroup)) {
    json_error('bad parameters');
}

echo (new Gazelle\Json\TGroup($tgroup, $Viewer, (new \Gazelle\Manager\Torrent)->setViewer($Viewer)))
    ->response();
