<?php

//------------- Expire old FL Tokens and clear cache where needed ------//
$sqltime = sqltime();
$DB->query("
	SELECT DISTINCT UserID
	FROM users_freeleeches
	WHERE Expired = FALSE
		AND Time < '$sqltime' - INTERVAL 4 DAY");

if ($DB->has_results()) {
    while (list($UserID) = $DB->next_record()) {
        $Cache->delete_value('users_tokens_'.$UserID[0]);
    }

    $DB->query("
		SELECT uf.UserID, t.info_hash
		FROM users_freeleeches AS uf
			JOIN torrents AS t ON uf.TorrentID = t.ID
		WHERE uf.Expired = FALSE
			AND uf.Time < '$sqltime' - INTERVAL 4 DAY");
    while (list($UserID, $InfoHash) = $DB->next_record(MYSQLI_NUM, false)) {
        Tracker::update_tracker('remove_token', array('info_hash' => rawurlencode($InfoHash), 'userid' => $UserID));
    }
    $DB->query("
		UPDATE users_freeleeches
		SET Expired = TRUE
		WHERE Time < '$sqltime' - INTERVAL 4 DAY
			AND Expired = FALSE");
}
