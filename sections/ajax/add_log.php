<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    json_error('bad parameters');
}
if ($torrent->uploaderId() != $Viewer->id() && !$Viewer->permitted('admin_add_log')) {
    json_error('Not your upload.');
}
if (empty($_FILES) || empty($_FILES['logfiles'])) {
    json_error('no log files uploaded');
}

(new Gazelle\Json\AddLog(
    $torrent,
    $Viewer,
    new Gazelle\Manager\TorrentLog(new Gazelle\File\RipLog, new Gazelle\File\RipLogHTML),
    new Gazelle\LogfileSummary($_FILES['logfiles']),
))
    ->setVersion(1)
    ->emit();
