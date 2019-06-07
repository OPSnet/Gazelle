<?php

//------------- Demote users --------------------------------------------//
sleep(10);
// Demote to Member when the ratio falls below 0.95 or they have less than 25 GB upload
$DemoteClasses = [POWER, ELITE, TORRENT_MASTER, POWER_TM, ELITE_TM];
$Query = $DB->query('
		SELECT ID
		FROM users_main
		WHERE PermissionID IN(' . implode(', ', $DemoteClasses) . ')
			AND (
				(Downloaded > 0 AND Uploaded / Downloaded < 0.95)
				OR Uploaded < 25 * 1024 * 1024 * 1024
			)');
echo "demoted 1\n";

$DB->query('
		UPDATE users_info AS ui
			JOIN users_main AS um ON um.ID = ui.UserID
		SET
			um.PermissionID = ' . MEMBER . ",
			ui.AdminComment = CONCAT('" . sqltime() . ' - Class changed to ' . Users::make_class_string(MEMBER) . " by System\n\n', ui.AdminComment)
		WHERE um.PermissionID IN (" . implode(', ', $DemoteClasses) . ')
			AND (
				(um.Downloaded > 0 AND um.Uploaded / um.Downloaded < 0.95)
				OR um.Uploaded < 25 * 1024 * 1024 * 1024
			)');
$DB->set_query_id($Query);
while (list($UserID) = $DB->next_record()) {
	/*$Cache->begin_transaction("user_info_$UserID");
	$Cache->update_row(false, array('PermissionID' => MEMBER));
	$Cache->commit_transaction(2592000);*/
	$Cache->delete_value("user_info_$UserID");
	$Cache->delete_value("user_info_heavy_$UserID");
	Misc::send_pm($UserID, 0, 'You have been demoted to '.Users::make_class_string(MEMBER), "You now only meet the requirements for the \"".Users::make_class_string(MEMBER)."\" user class.\n\nTo read more about ".SITE_NAME."'s user classes, read [url=".site_url()."wiki.php?action=article&amp;name=userclasses]this wiki article[/url].");
}
echo "demoted 2\n";

// Demote to User when the ratio drops below 0.65
$DemoteClasses = [MEMBER, POWER, ELITE, TORRENT_MASTER, POWER_TM, ELITE_TM];
$Query = $DB->query('
		SELECT ID
		FROM users_main
		WHERE PermissionID IN(' . implode(', ', $DemoteClasses) . ')
			AND Uploaded / Downloaded < 0.65');
echo "demoted 3\n";
$DB->query('
		UPDATE users_info AS ui
			JOIN users_main AS um ON um.ID = ui.UserID
		SET
			um.PermissionID = ' . USER . ",
			ui.AdminComment = CONCAT('" . sqltime() . ' - Class changed to ' . Users::make_class_string(USER) . " by System\n\n', ui.AdminComment)
		WHERE um.PermissionID IN (" . implode(', ', $DemoteClasses) . ')
			AND (um.Downloaded > 0 AND um.Uploaded / um.Downloaded < 0.65)');
$DB->set_query_id($Query);
while (list($UserID) = $DB->next_record()) {
	/*$Cache->begin_transaction("user_info_$UserID");
	$Cache->update_row(false, array('PermissionID' => USER));
	$Cache->commit_transaction(2592000);*/
	$Cache->delete_value("user_info_$UserID");
	$Cache->delete_value("user_info_heavy_$UserID");
	Misc::send_pm($UserID, 0, 'You have been demoted to '.Users::make_class_string(USER), "You now only meet the requirements for the \"".Users::make_class_string(USER)."\" user class.\n\nTo read more about ".SITE_NAME."'s user classes, read [url=".site_url()."wiki.php?action=article&amp;name=userclasses]this wiki article[/url].");
}
echo "demoted 4\n";
