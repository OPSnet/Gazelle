<?php
/** @phpstan-var \Gazelle\User $Viewer */

use OrpheusNET\Logchecker\Logchecker;

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent())->findById((int)$_GET['torrentid']);
$logId = (int)$_GET['logid'];
if (is_null($torrent) || !$logId) {
    error(404);
}

$logpath = (new Gazelle\File\RipLog())->path([$torrent->id(), $logId]);
$logfile = new Gazelle\Logfile($logpath, basename($logpath));
(new Gazelle\File\RipLogHTML())->put($logfile->text(), [$torrent->id(), $logId]);

$torrent->rescoreLog($logId, $logfile, Logchecker::getLogcheckerVersion());

header('Location: ' . $torrent->location());
