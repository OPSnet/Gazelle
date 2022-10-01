<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    $json->failure('bad parameters');
    exit;
}
if (empty($_FILES) || empty($_FILES['logfiles'])) {
    $json->failure('no log files uploaded');
    exit;
}

(new Gazelle\Json\AddLog($torrent, $Viewer, $_FILES['logfiles']))
    ->setVersion(1)
    ->emit();
