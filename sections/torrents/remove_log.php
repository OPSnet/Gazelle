<?php

authorize();
if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent())->findById((int)$_GET['torrentid']);
$logId = (int)$_GET['logid'];
if (is_null($torrent) || !$logId) {
    error(404);
}

(new Gazelle\File\RipLog())->remove([$torrent->id(), $logId]);
(new Gazelle\Log())->torrent($torrent, $Viewer, "Riplog ID $logId removed from torrent {$torrent->id()}");
$torrent->clearLog($logId);

header('Location: ' . $torrent->location());
