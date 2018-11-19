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
G::$DB->prepared_query("DELETE FROM torrents_logs WHERE TorrentID=? AND LogID=?", $TorrentID, $LogID);

G::$DB->prepared_query("SELECT COUNT(*) FROM torrents_logs WHERE TorrentID=?", $TorrentID);
list($Count) = G::$DB->fetch_record();

if ($Count > 0) {
    G::$DB->prepared_query("
UPDATE torrents AS t
LEFT JOIN (
  SELECT
	  TorrentID,
	  MIN(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
	  MIN(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
	FROM torrents_logs
	GROUP BY TorrentID
  ) AS tl ON t.ID = tl.TorrentID
SET t.LogScore = tl.Score, t.LogChecksum=tl.Checksum
WHERE t.ID = ?", $TorrentID);
} else {
    G::$DB->prepared_query("UPDATE torrents SET HasLogDB = 0, LogScore = 100, LogChecksum = 1 WHERE ID=?", $TorrentID);
}

$Cache->delete_value("torrent_group_{$GroupID}");
$Cache->delete_value("torrents_details_{$GroupID}");

header("Location: torrents.php?torrentid={$TorrentID}");
