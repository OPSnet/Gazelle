<?php

//------------- Rescore 0.95 logs of disabled users

$LogQuery = $DB->query("
			SELECT DISTINCT t.ID
			FROM torrents AS t
				JOIN users_main AS um ON t.UserID = um.ID
				JOIN torrents_logs_new AS tl ON tl.TorrentID = t.ID
			WHERE um.Enabled = '2'
				AND t.HasLog = '1'
				AND LogScore = 100
				AND Log LIKE 'EAC extraction logfile from%'");
$Details = array();
$Details[] = "Ripped with EAC v0.95, -1 point [1]";
$Details = serialize($Details);
while (list($TorrentID) = $DB->next_record()) {
    $DB->query("
			UPDATE torrents
			SET LogScore = 99
			WHERE ID = $TorrentID");
    $DB->query("
			UPDATE torrents_logs_new
			SET Score = 99, Details = '$Details'
			WHERE TorrentID = $TorrentID");
}

sleep(5);
