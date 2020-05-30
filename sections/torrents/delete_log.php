<?php

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

if ($TorrentID === 0 || $LogID === 0) {
    error(404);
}

$GroupID = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
if (!$GroupID) {
    error(404);
}

@unlink(SERVER_ROOT."logs/{$TorrentID}_{$LogID}.log");

Torrents::clear_log($TorrentID, $LogID);
Torrents::set_logscore($TorrentID, $GroupID);
Torrents::write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "Riplog ID $LogID removed from torrent $TorrentID", 0);

header("Location: torrents.php?torrentid={$TorrentID}");
