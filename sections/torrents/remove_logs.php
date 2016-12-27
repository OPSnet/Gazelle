<?
$TorrentID = $_GET['torrentid'];
if (!$TorrentID || !is_number($TorrentID)) {
    error(404);
}

$DB->query("SELECT LogID FROM torrents_logs_new WHERE TorrentID = ".$TorrentID);

if (!$DB->has_results()) {
    error('Torrent has no logs.');
}

if ($LoggedUser['ID'] != $UserID && !check_perms('torrents_delete')) {
    error(403);
}

$DB->query("DELETE FROM torrents_logs_new WHERE TorrentID=".$TorrentID);
$DB->query("UPDATE torrents SET HasLog='1', HasCue='0', LogScore=0 WHERE ID=".$TorrentID);

header('Location: '.$_SERVER['HTTP_REFERER']);
