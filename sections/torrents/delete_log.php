<?php

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

if ($TorrentID === 0 || $LogID === 0) {
    error(404);
}

G::$DB->prepared_query("SELECT GroupID FROM torrents WHERE ID=?", $TorrentID);
if (!G::$DB->has_results()) {
    error(404);
}
list($GroupID) = G::$DB->fetch_record();

@unlink(SERVER_ROOT."logs/{$TorrentID}_{$LogID}.log");

Torrents::clear_log($TorrentID, $LogID);
Torrents::set_logscore($TorrentID, $GroupID);

header("Location: torrents.php?torrentid={$TorrentID}");
