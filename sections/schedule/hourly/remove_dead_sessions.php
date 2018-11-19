<?php

//------------- Remove dead sessions ---------------------------------------//
sleep(3);

$AgoMins = time_minus(60 * 30);
$AgoDays = time_minus(3600 * 24 * 30);

$SessionQuery = $DB->query("
			SELECT UserID, SessionID
			FROM users_sessions
			WHERE (LastUpdate < '$AgoDays' AND KeepLogged = '1')
				OR (LastUpdate < '$AgoMins' AND KeepLogged = '0')");
$DB->query("
		DELETE FROM users_sessions
		WHERE (LastUpdate < '$AgoDays' AND KeepLogged = '1')
			OR (LastUpdate < '$AgoMins' AND KeepLogged = '0')");

$DB->set_query_id($SessionQuery);
while (list($UserID, $SessionID) = $DB->next_record()) {
    $Cache->begin_transaction("users_sessions_$UserID");
    $Cache->delete_row($SessionID);
    $Cache->commit_transaction(0);
}
