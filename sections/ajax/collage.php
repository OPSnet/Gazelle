<?php

$json = new Gazelle\Json\Collage;

$collageMan = new Gazelle\Manager\Collage;
$collage = $collageMan->findById((int)($_GET['id'] ?? 0));
if (is_null($collage)) {
    $json->failure('bad parameters');
    exit;
}

$json->setVersion(2)
    ->setCollage($collage)
    ->setTGroupManager(new Gazelle\Manager\TGroup)
    ->setTorrentManager(new Gazelle\Manager\Torrent)
    ->setUser($Viewer)
    ->emit();
