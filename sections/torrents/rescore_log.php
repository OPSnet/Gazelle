<?php

use OrpheusNET\Logchecker\Logchecker;

if (!check_perms('users_mod')) {
    error(403);
}

$torrentId = (int)$_GET['torrentid'];
$logId     = (int)$_GET['logid'];
if (!$DB->scalar("
    SELECT 1 FROM torrents_logs WHERE LogID = ? AND TorrentID = ?
    ", $logId, $torrentId)
) {
    error(404);
}
$groupId = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $torrentId);
if (!$groupId) {
    error(404);
}

$logpath = (new Gazelle\File\RipLog)->path([$torrentId, $logId]);
$logfile = new Gazelle\Logfile($logpath, basename($logpath));
(new Gazelle\File\RipLogHTML)->put($logfile->text(), [$torrentId, $logId]);

$torMan = new Gazelle\Manager\Torrent;
$torMan->rescoreLog($groupId, $torrentId, $logId, $logfile, Logchecker::getLogcheckerVersion());

header("Location: torrents.php?torrentid={$torrentId}");
