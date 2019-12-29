<?php

//------------- Expire old FL Tokens and clear cache where needed ------//
$now = sqltime();
$expiry = FREELEECH_TOKEN_EXPIRY_DAYS;
$DB->query("
    SELECT DISTINCT UserID
    FROM users_freeleeches
    WHERE Expired = FALSE
        AND Time < '$now' - INTERVAL $expiry DAY
");

if ($DB->has_results()) {
    while (list($UserID) = $DB->next_record()) {
        $Cache->delete_value('users_tokens_'.$UserID[0]);
    }

    $DB->query("
        SELECT uf.UserID, t.info_hash
        FROM users_freeleeches AS uf
        INNER JOIN torrents AS t ON (uf.TorrentID = t.ID)
        WHERE uf.Expired = FALSE
            AND uf.Time < '$now' - INTERVAL $expiry DAY
    ");
    while (list($UserID, $InfoHash) = $DB->next_record(MYSQLI_NUM, false)) {
        Tracker::update_tracker('remove_token', ['info_hash' => rawurlencode($InfoHash), 'userid' => $UserID]);
    }
    $DB->query("
        UPDATE users_freeleeches
        SET Expired = TRUE
        WHERE Time < '$now' - INTERVAL $expiry DAY
            AND Expired = FALSE
    ");
}
