<?php

use OrpheusNET\Logchecker\Logchecker;

if (!check_perms('users_mod')) {
    error(403);
}

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

$DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
if (!$GroupID) {
    error(404);
}

$DB->prepared_query('SELECT 1 FROM torrents_logs WHERE LogID = ? AND TorrentID = ?', $LogID, $TorrentID);
if (!$DB->has_results()) {
    error(404);
}

$ripFiler = new \Gazelle\File\RipLog($DB, $Cache);

$logpath = $ripFiler->pathLegacy([$TorrentID, $LogID]);
$logfile = new \Gazelle\Logfile($logpath, basename($logpath));
copy($ripFiler->pathLegacy([$TorrentID, $LogID]), $ripFiler->path([$TorrentID, $LogID]));

$htmlFiler = new \Gazelle\File\RipLogHTML($DB, $Cache);
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
    ", $Logfile->score(), $Logfile->checksumStatus(), $Logfile->checksumState(), $Logfile->ripper(), $Logfile->ripperVersion(),
        $Logfile->language(), Logchecker::getLogcheckerVersion(),
        $Logfile->detailsAsString(), $Logfile->text(),
        $LogID, $TorrentID
);
Torrents::set_logscore($TorrentID, $GroupID);

header("Location: torrents.php?torrentid={$TorrentID}");
