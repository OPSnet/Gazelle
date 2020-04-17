<?php

if (!check_perms('torrents_delete')) {
    error(403);
}

$TorrentID = intval($_GET['torrentid']);
if (!$TorrentID) {
    error(404);
}
if (!$DB->scalar('SELECT 1 FROM torrents_logs WHERE TorrentID = ?', $TorrentID)) {
    error('Torrent has no logs.');
}

$DB->prepared_query('DELETE FROM torrents_logs WHERE TorrentID = ?', $TorrentID);
$DB->prepared_query("UPDATE torrents SET HasLog='1', HasLogDB='0', LogScore=0, LogChecksum='0' WHERE ID = ?", $TorrentID);

$GroupID = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
$DB->prepared_query('
    INSERT INTO group_log (GroupID, TorrentID, UserID, Info, Time) VALUES(?, ?, ?, ?, now())
    ', $GroupID, $TorrentID, $LoggedUser['ID'], 'Logs removed from torrent'
);

$Cache->deleteMulti(["torrent_group_$GroupID", "torrents_details_$GroupID"]);

header('Location: ' . empty($_SERVER['HTTP_REFERER']) ? "torrents.php?torrentid={$TorrentID}" : $_SERVER['HTTP_REFERER']);
