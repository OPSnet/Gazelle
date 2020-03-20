<?php

use OrpheusNET\Logchecker\Logchecker;

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

$Log = new Logchecker();
$LogPath = SERVER_ROOT."/logs/{$TorrentID}_{$LogID}.log";
$Log->new_file($LogPath);
list($Score, $Details, $Checksum, $LogText) = $Log->parse();
$Details = trim(implode("\r\n", $Details));

$DB->prepared_query(
    'UPDATE torrents_logs SET Log = ?, Details = ?, Score = ?, `Checksum` = ?, Adjusted = ? WHERE LogID = ? AND TorrentID = ?',
    $LogText, $Details, $Score, $Checksum, 0, $LogID, $TorrentID
);

Torrents::set_logscore($TorrentID, $GroupID);

header("Location: torrents.php?torrentid={$TorrentID}");
