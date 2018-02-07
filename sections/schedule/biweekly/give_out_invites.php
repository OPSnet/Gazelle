<?php

//------------- Give out invites! ---------------------------------------//

/*
Power Users have a cap of 2 invites. Elites have a cap of 4.
Every month, on the 8th and the 22nd, each PU/Elite user gets one invite up to their max.

Then, every month, on the 8th and the 22nd, we give out bonus invites like this:

Every Power User or Elite whose total invitee ratio is above 0.75 and total invitee upload is over 2 GBs gets one invite.
Every Elite whose total invitee ratio is above 2.0 and total invitee upload is over 10 GBs gets one more invite.
Every Elite whose total invitee ratio is above 3.0 and total invitee upload is over 20 GBs gets yet one more invite.

This cascades, so if you qualify for the last bonus group, you also qualify for the first two and will receive three bonus invites.

The bonus invites cannot put a user over their cap.

*/

$DB->query("
		SELECT ID
		FROM users_main AS um
			JOIN users_info AS ui ON ui.UserID = um.ID
		WHERE um.Enabled = '1'
			AND ui.DisableInvites = '0'
			AND ((um.PermissionID = ".POWER."
					AND um.Invites < 2
				 ) OR (um.PermissionID = ".ELITE."
					AND um.Invites < 4)
				)");
$UserIDs = $DB->collect('ID');
if (count($UserIDs) > 0) {
	foreach ($UserIDs as $UserID) {
		$Cache->begin_transaction("user_info_heavy_$UserID");
		$Cache->update_row(false, array('Invites' => '+1'));
		$Cache->commit_transaction(0);
	}
	$DB->query('
			UPDATE users_main
			SET Invites = Invites + 1
			WHERE ID IN ('.implode(',', $UserIDs).')');
}

$BonusReqs = array(
	array(0.75, 2 * 1024 * 1024 * 1024),
	array(2.0, 10 * 1024 * 1024 * 1024),
	array(3.0, 20 * 1024 * 1024 * 1024));

// Since MySQL doesn't like subselecting from the target table during an update, we must create a temporary table.

$DB->query("
		CREATE TEMPORARY TABLE temp_sections_schedule_index
		SELECT SUM(Uploaded) AS Upload, SUM(Downloaded) AS Download, Inviter
		FROM users_main AS um
			JOIN users_info AS ui ON ui.UserID = um.ID
		GROUP BY Inviter");

foreach ($BonusReqs as $BonusReq) {
	list($Ratio, $Upload) = $BonusReq;
	$DB->query("
			SELECT ID
			FROM users_main AS um
				JOIN users_info AS ui ON ui.UserID = um.ID
				JOIN temp_sections_schedule_index AS u ON u.Inviter = um.ID
			WHERE u.Upload > $Upload
				AND u.Upload / u.Download > $Ratio
				AND um.Enabled = '1'
				AND ui.DisableInvites = '0'
				AND ((um.PermissionID = ".POWER.'
						AND um.Invites < 2
					 ) OR (um.PermissionID = '.ELITE.'
						AND um.Invites < 4)
					)');
	$UserIDs = $DB->collect('ID');
	if (count($UserIDs) > 0) {
		foreach ($UserIDs as $UserID) {
			$Cache->begin_transaction("user_info_heavy_$UserID");
			$Cache->update_row(false, array('Invites' => '+1'));
			$Cache->commit_transaction(0);
		}
		$DB->query('
				UPDATE users_main
				SET Invites = Invites + 1
				WHERE ID IN ('.implode(',', $UserIDs).')');
	}
}