<?php

//We use this to control 6 hour freeleeches.
// They're actually 7 hours, but don't tell anyone.

$TimeMinus = time_minus(3600 * 7);

$DB->query("
	SELECT DISTINCT GroupID
	FROM torrents
	WHERE FreeTorrent = '1'
		AND FreeLeechType = '3'
		AND Time < '$TimeMinus'");
while (list($GroupID) = $DB->next_record()) {
    $Cache->delete_value("torrents_details_$GroupID");
    $Cache->delete_value("torrent_group_$GroupID");
}
$DB->query("
	UPDATE torrents
	SET FreeTorrent = '0',
		FreeLeechType = '0'
	WHERE FreeTorrent = '1'
		AND FreeLeechType = '3'
		AND Time < '$TimeMinus'");

sleep(5);
