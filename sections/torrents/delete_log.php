<?php

$torrentId = (int)$_GET['torrentid'];
$logId = (int)$_GET['logid'];

if (!$torrentId || !$logId) {
    error(404);
}

$groupId = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $torrentId);
if (!$groupId) {
    error(404);
}

(new Gazelle\File\RipLog)->remove([$torrentId, $logId]);
(new Gazelle\Log)->torrent($groupId, $torrentId, $LoggedUser['ID'], "Riplog ID $logId removed from torrent $torrentId");

Torrents::clear_log($torrentId, $logId);
Torrents::set_logscore($torrentId, $groupId);

header("Location: torrents.php?torrentid={$torrentId}");
