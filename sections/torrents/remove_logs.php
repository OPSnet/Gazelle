<?php

if (!check_perms('torrents_delete')) {
    error(403);
}

$TorrentID = intval($_GET['torrentid']);
if (!$TorrentID) {
    error(404);
}

$ripFiler = new \Gazelle\File\RipLog($DB, $Cache);
$ripFiler->remove([$TorrentID, null]);

$htmlFiler = new \Gazelle\File\RipLogHTML($DB, $Cache);
$htmlFiler->remove([$TorrentID, null]);

if (!$DB->scalar('SELECT 1 FROM torrents_logs WHERE TorrentID = ?', $TorrentID)) {
    error('Torrent has no logs.');
}

$DB->prepared_query('DELETE FROM torrents_logs WHERE TorrentID = ?', $TorrentID);
$DB->prepared_query("UPDATE torrents SET HasLog='1', HasLogDB='0', LogScore=0, LogChecksum='0' WHERE ID = ?", $TorrentID);

$GroupID = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
Torrents::write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "All logs removed from torrent", 0);

$Cache->deleteMulti(["torrent_group_$GroupID", "torrents_details_$GroupID"]);
header('Location: ' . (empty($_SERVER['HTTP_REFERER']) ? "torrents.php?torrentid={$TorrentID}" : $_SERVER['HTTP_REFERER']));
