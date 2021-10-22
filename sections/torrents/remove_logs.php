<?php

if (!$Viewer->permitted('torrents_delete')) {
    error(403);
}

$TorrentID = (int)$_GET['torrentid'];
if (!$TorrentID) {
    error(404);
}

(new Gazelle\File\RipLog)->remove([$TorrentID, null]);
(new Gazelle\File\RipLogHTML)->remove([$TorrentID, null]);

$DB->prepared_query('DELETE FROM torrents_logs WHERE TorrentID = ?', $TorrentID);
$DB->prepared_query("UPDATE torrents SET HasLog='1', HasLogDB='0', LogScore=0, LogChecksum='0' WHERE ID = ?", $TorrentID);

$GroupID = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
(new Gazelle\Log)->torrent($GroupID, $TorrentID, $Viewer->id(), "All logs removed from torrent");

$Cache->deleteMulti(["torrent_group_$GroupID", "torrents_details_$GroupID", "tg2_$GroupID", "tlist_$GroupID"]);
header('Location: ' . redirectUrl("torrents.php?torrentid={$TorrentID}"));
