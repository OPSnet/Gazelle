<?php

use OrpheusNET\Logchecker\Logchecker;

if (!check_perms('users_mod')) {
    error(403);
}

$torrentId = (int)$_GET['torrentid'];
$logId     = (int)$_GET['logid'];
if (!$torrentId || !$logId) {
    error(404);
}
$groupId = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $torrentId);
if (!$groupId) {
    error(404);
}
if (!$DB->scalar('SELECT 1 FROM torrents_logs WHERE LogID = ? AND TorrentID = ?',
        $logId, $torrentId)) {
    error(404);
}

$logpath = (new Gazelle\File\RipLog)->path([$torrentId, $logId]);
$logfile = new Gazelle\Logfile($logpath, basename($logpath));
(new Gazelle\File\RipLogHTML)->put($logfile->text(), [$torrentId, $logId]);

$DB->prepared_query("
    UPDATE torrents_logs SET
        Score = ?,
        `Checksum` = ?,
        ChecksumState = ?,
        Ripper = ?,
        RipperVersion = ?,
        `Language` = ?,
        LogcheckerVersion = ?,
        Details = ?,
        Adjusted = '0'
    WHERE LogID = ? AND TorrentID = ?
    ", $logfile->score(), $logfile->checksumStatus(), $logfile->checksumState(), $logfile->ripper(), $logfile->ripperVersion(),
        $logfile->language(), Logchecker::getLogcheckerVersion(),
        $logfile->detailsAsString(),
        $logId, $torrentId
);
Torrents::set_logscore($torrentId, $groupId);

header("Location: torrents.php?torrentid={$torrentId}");
