<?php

//------------- Disable downloading ability of users on ratio watch
$UserQuery = $DB->query("
			SELECT ID, torrent_pass
			FROM users_info AS i
				JOIN users_main AS m ON m.ID = i.UserID
			WHERE i.RatioWatchEnds != '0000-00-00 00:00:00'
				AND i.RatioWatchEnds < '$sqltime'
				AND m.Enabled = '1'
				AND m.can_leech != '0'");

$UserIDs = $DB->collect('ID');
if (count($UserIDs) > 0) {
    $DB->query("
			UPDATE users_info AS i
				JOIN users_main AS m ON m.ID = i.UserID
			SET	m.can_leech = '0',
				i.AdminComment = CONCAT('$sqltime - Leeching ability disabled by ratio watch system - required ratio: ', m.RequiredRatio, '\n\n', i.AdminComment)
			WHERE m.ID IN(".implode(',', $UserIDs).')');



    $DB->query("
			DELETE FROM users_torrent_history
			WHERE UserID IN (".implode(',', $UserIDs).')');
}

foreach ($UserIDs as $UserID) {
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, array('RatioWatchDownload' => 0, 'CanLeech' => 0));
    $Cache->commit_transaction(0);
    Misc::send_pm($UserID, 0, 'Your downloading privileges have been disabled', "As you did not raise your ratio in time, your downloading privileges have been revoked. You will not be able to download any torrents until your ratio is above your new required ratio.");
    echo "Ratio watch disabled: $UserID\n";
}

$DB->set_query_id($UserQuery);
$Passkeys = $DB->collect('torrent_pass');
foreach ($Passkeys as $Passkey) {
    Tracker::update_tracker('update_user', array('passkey' => $Passkey, 'can_leech' => '0'));
}