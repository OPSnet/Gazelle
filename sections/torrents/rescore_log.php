<?php

use OrpheusNET\Logchecker\Logchecker;

if (!check_perms('users_mod')) {
    error(403);
}

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

$GroupID = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
if (!$GroupID) {
    error(404);
}

if (!$DB->scalar('SELECT 1 FROM torrents_logs WHERE LogID = ? AND TorrentID = ?',
        $LogID, $TorrentID)) {
    error(404);
}

$ripFiler = new \Gazelle\File\RipLog;

$logpath = $ripFiler->pathLegacy([$TorrentID, $LogID]);
$logfile = new \Gazelle\Logfile($logpath, basename($logpath));

copy($ripFiler->pathLegacy([$TorrentID, $LogID]), $ripFiler->path([$TorrentID, $LogID]));
$htmlFiler = new \Gazelle\File\RipLogHTML;
$htmlFiler->put($logfile->text(), [$TorrentID, $LogID]);

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
        Log = ?,
        Adjusted = '0'
    WHERE LogID = ? AND TorrentID = ?
    ", $logfile->score(), $logfile->checksumStatus(), $logfile->checksumState(), $logfile->ripper(), $logfile->ripperVersion(),
        $logfile->language(), Logchecker::getLogcheckerVersion(),
        $logfile->detailsAsString(), $logfile->text(),
        $LogID, $TorrentID
);
Torrents::set_logscore($TorrentID, $GroupID);

header("Location: torrents.php?torrentid={$TorrentID}");
