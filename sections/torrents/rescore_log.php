<?php

use OrpheusNET\Logchecker\Logchecker;

if (!check_perms('users_mod')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
$logId = (int)$_GET['logid'];
if (is_null($torrent) || !$logId) {
    error(404);
}

$logpath = (new Gazelle\File\RipLog)->path([$torrent->id(), $logId]);
$logfile = new Gazelle\Logfile($logpath, basename($logpath));
(new Gazelle\File\RipLogHTML)->put($logfile->text(), [$torrent->id(), $logId]);

$torrent->rescoreLog($logId, $logfile, Logchecker::getLogcheckerVersion());

header("Location: torrents.php?torrentid=" . $torrent->id());
