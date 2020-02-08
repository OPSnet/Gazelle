<?php

//------------- Disable unconfirmed users ------------------------------//
// get a list of user IDs for clearing cache keys
$DB->query("
    SELECT UserID
    FROM users_info AS ui
    INNER JOIN users_main AS um ON (um.ID = ui.UserID)
    WHERE um.LastAccess = '0000-00-00 00:00:00'
        AND ui.JoinDate < now() - INTERVAL 7 DAY
        AND um.Enabled != '2'");
$UserIDs = $DB->collect('UserID');

// disable the users
$DB->query("
    UPDATE users_info AS ui
    INNER JOIN users_main AS um ON (um.ID = ui.UserID)
    SET um.Enabled = '2',
        ui.BanDate = '$sqltime',
        ui.BanReason = '3',
        ui.AdminComment = CONCAT('$sqltime - Disabled for inactivity (never logged in)\n\n', ui.AdminComment)
    WHERE um.LastAccess = '0000-00-00 00:00:00'
        AND ui.JoinDate < now() - INTERVAL 7 DAY
        AND um.Enabled != '2'");
if ($DB->has_results()) {
    Users::flush_enabled_users_count();
}

// clear the appropriate cache keys
foreach ($UserIDs as $UserID) {
    $Cache->delete_value("user_info_$UserID");
}

echo count($UserIDs) . " disabled unconfirmed\n";
