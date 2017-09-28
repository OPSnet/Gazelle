<?php

//------------- Remove expired warnings ---------------------------------//
$DB->query("
		SELECT UserID
		FROM users_info
		WHERE Warned < '$sqltime'");
while (list($UserID) = $DB->next_record()) {
	$Cache->begin_transaction("user_info_$UserID");
	$Cache->update_row(false, array('Warned' => '0000-00-00 00:00:00'));
	$Cache->commit_transaction(2592000);
}

$DB->query("
		UPDATE users_info
		SET Warned = '0000-00-00 00:00:00'
		WHERE Warned < '$sqltime'");
