<?
$TorrentID = $_GET['torrentid'];

if (!$TorrentID || !is_number($TorrentID)) {
    error(404);
}

if (!check_perms('torrents_delete')) {
    error(403);
}

$DB->query("SELECT LogID FROM torrents_logs_new WHERE TorrentID = ".$TorrentID);

if (!$DB->has_results()) {
    error('Torrent has no logs.');
}

$DB->query("SELECT GroupID FROM torrents WHERE ID = ".$TorrentID);
list($GroupID) = $DB->next_record();

$DB->query("DELETE FROM torrents_logs_new WHERE TorrentID=".$TorrentID);
$DB->query("UPDATE torrents SET HasLog='1', HasCue='0', LogScore=0 WHERE ID=".$TorrentID);
$DB->query(sprintf("INSERT INTO group_log (GroupID, TorrentID, UserID, Time, Info) VALUES(%d, %d, %d, '%s', 'Logs removed from torrent')",
    $GroupID, $TorrentID, $LoggedUser['ID'], sqltime()));

header('Location: '.$_SERVER['HTTP_REFERER']);
