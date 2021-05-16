<?php

if (!check_perms('users_mod')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
$logId = (int)$_GET['logid'];
if (is_null($torrent) || !$logId) {
    error(404);
}

(new Gazelle\File\RipLog)->remove([$torrent->id(), $logId]);
(new Gazelle\Log)->torrent($torrent->groupId(), $torrent->id(), $LoggedUser['ID'], "Riplog ID $logId removed from torrent $torrentId");

Torrents::clear_log($torrent->id(), $logId);
$torrent->modifyLogscore();

header("Location: torrents.php?torrentid=" . $torrent->id());
