<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    json_error('bad parameters');
}
if (empty($_FILES) || empty($_FILES['logfiles'])) {
    json_error('no log files uploaded');
}

(new Gazelle\Json\AddLog($torrent, $Viewer, $_FILES['logfiles']))
    ->setVersion(1)
    ->emit();
