<?php

use OrpheusNET\Logchecker\Logchecker;
use Gazelle\Logfile;

if (!check_perms('users_mod')) {
    error(403);
}

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

$DB->prepared_query('SELECT GroupID FROM torrents WHERE ID= ?', $TorrentID);
list($GroupID) = $DB->fetch_record();
if (!$GroupID) {
    error(404);
}

$DB->prepared_query('SELECT 1 FROM torrents_logs WHERE LogID = ? AND TorrentID = ?', $LogID, $TorrentID);
if (!$DB->has_results()) {
    error(404);
}

$LogPath = SERVER_ROOT_LIVE."/logs/{$TorrentID}_{$LogID}.log";
$Logfile = new Logfile($LogPath);

$DB->prepared_query(
    'UPDATE torrents_logs SET
        `Log` = ?, Details = ?, Score = ?, `Checksum` = ?,
        Adjusted = ?, Ripper = ?, RipperVersion = ?, `Language` = ?, ChecksumState = ?, LogcheckerVersion = ?
    WHERE LogID = ? AND TorrentID = ?',
    $Logfile->text(), $Logfile->detailsAsString(), $Logfile->score(), $Logfile->checksumStatus(),
    0, $Logfile->ripper(), $Logfile->ripperVersion(), $Logfile->language(), $Logfile->checksumState(), Logchecker::getLogcheckerVersion(),
    $LogID, $TorrentID
);

Torrents::set_logscore($TorrentID, $GroupID);

header("Location: torrents.php?torrentid={$TorrentID}");
