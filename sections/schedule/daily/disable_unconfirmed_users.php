<?php

//------------- Disable unconfirmed users ------------------------------//
sleep(10);
// get a list of user IDs for clearing cache keys
$DB->query("
		SELECT UserID
		FROM users_info AS ui
			JOIN users_main AS um ON um.ID = ui.UserID
		WHERE um.LastAccess = '0000-00-00 00:00:00'
			AND ui.JoinDate < '".time_minus(60 * 60 * 24 * 7)."'
			AND um.Enabled != '2'");
$UserIDs = $DB->collect('UserID');

// disable the users
$DB->query("
		UPDATE users_info AS ui
			JOIN users_main AS um ON um.ID = ui.UserID
		SET um.Enabled = '2',
			ui.BanDate = '$sqltime',
			ui.BanReason = '3',
			ui.AdminComment = CONCAT('$sqltime - Disabled for inactivity (never logged in)\n\n', ui.AdminComment)
		WHERE um.LastAccess = '0000-00-00 00:00:00'
			AND ui.JoinDate < '".time_minus(60 * 60 * 24 * 7)."'
			AND um.Enabled != '2'");
$Cache->decrement('stats_user_count', $DB->affected_rows());

// clear the appropriate cache keys
foreach ($UserIDs as $UserID) {
    $Cache->delete_value("user_info_$UserID");
}

echo "disabled unconfirmed\n";