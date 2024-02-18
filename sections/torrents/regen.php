<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_REQUEST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}
$torrent->regenerateFilelist(
    new Gazelle\File\Torrent(),
    new OrpheusNET\BencodeTorrent\BencodeTorrent()
);

header("Location: " . $torrent->location());
