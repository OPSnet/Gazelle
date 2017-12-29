<?php
if (!check_perms('users_mod')) {
	error(403);
}

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

$DB->query("SELECT GroupID FROM torrents WHERE ID='{$TorrentID}'");
if (!$DB->has_results()) {
	error(404);
}
$DB->query("SELECT * FROM torrents_logs WHERE LogID='{$LogID}' AND TorrentID='{$TorrentID}'");
if (!$DB->has_results()) {
	error(404);
}
$Log = $DB->next_record(MYSQLI_ASSOC);

$LogPath = SERVER_ROOT."/logs/{$TorrentID}_{$LogID}.log";
$Log = new Logchecker();
$Log->new_file($LogPath);
list($Score, $Details, $Checksum, $LogText) = $Log->parse();
$Details = trim(implode("\r\n", $Details));
$DetailsArray[] = $Details;
$LogChecksum = min(intval($Checksum), $LogChecksum);
$DB->query("UPDATE torrents_logs SET Log='".db_string($LogText)."', Details='".db_string($Details)."', Score='{$Score}', `Checksum`='{$Checksum}', Adjusted='0' WHERE LogID='{$LogID}' AND TorrentID='{$TorrentID}'");

$DB->query("
UPDATE torrents AS t
JOIN (
	SELECT
		TorrentID,
		MIN(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
		MIN(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
	FROM torrents_logs
	GROUP BY TorrentID
 ) AS tl ON t.ID = tl.TorrentID
SET t.LogScore = tl.Score, t.LogChecksum=tl.Checksum
WHERE t.ID = {$TorrentID}");

$Cache->delete_value("torrent_group_{$GroupID}");
$Cache->delete_value("torrents_details_{$GroupID}");

header("Location: torrents.php?torrentid={$TorrentID}");
