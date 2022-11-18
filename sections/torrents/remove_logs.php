<?php

if (!$Viewer->permitted('torrents_delete')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_GET['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

$torrent->removeAllLogs(
    $Viewer,
    new Gazelle\File\RipLog,
    new Gazelle\File\RipLogHTML,
    new Gazelle\Log,
);
header('Location: ' . $torrent->location());
