<?php

// Here is where we manage ratio watch

$OffRatioWatch = [];
$OnRatioWatch = [];

// Take users off ratio watch and enable leeching
$UserQuery = $DB->query("
			SELECT
				m.ID,
				torrent_pass
			FROM users_info AS i
				JOIN users_main AS m ON m.ID = i.UserID
			WHERE m.Downloaded > 0
				AND m.Uploaded / m.Downloaded >= m.RequiredRatio
				AND i.RatioWatchEnds != '0000-00-00 00:00:00'
				AND m.can_leech = '0'
				AND m.Enabled = '1'");
$OffRatioWatch = $DB->collect('ID');
if (count($OffRatioWatch) > 0) {
    $DB->query("
			UPDATE users_info AS ui
				JOIN users_main AS um ON um.ID = ui.UserID
			SET ui.RatioWatchEnds = '0000-00-00 00:00:00',
				ui.RatioWatchDownload = '0',
				um.can_leech = '1',
				ui.AdminComment = CONCAT('$sqltime - Leeching re-enabled by adequate ratio.\n\n', ui.AdminComment)
			WHERE ui.UserID IN(".implode(',', $OffRatioWatch).')');
}

foreach ($OffRatioWatch as $UserID) {
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, ['RatioWatchEnds' => '0000-00-00 00:00:00', 'RatioWatchDownload' => '0', 'CanLeech' => 1]);
    $Cache->commit_transaction(0);
    Misc::send_pm($UserID, 0, 'You have been taken off Ratio Watch', "Congratulations! Feel free to begin downloading again.\n To ensure that you do not get put on ratio watch again, please read the rules located [url=".site_url()."rules.php?p=ratio]here[/url].\n");
    echo "Ratio watch off: $UserID\n";
}
$DB->set_query_id($UserQuery);
$Passkeys = $DB->collect('torrent_pass');
foreach ($Passkeys as $Passkey) {
    Tracker::update_tracker('update_user', ['passkey' => $Passkey, 'can_leech' => '1']);
}

// Take users off ratio watch
$UserQuery = $DB->query("
				SELECT m.ID, torrent_pass
				FROM users_info AS i
					JOIN users_main AS m ON m.ID = i.UserID
				WHERE m.Downloaded > 0
					AND m.Uploaded / m.Downloaded >= m.RequiredRatio
					AND i.RatioWatchEnds != '0000-00-00 00:00:00'
					AND m.Enabled = '1'");
$OffRatioWatch = $DB->collect('ID');
if (count($OffRatioWatch) > 0) {
    $DB->query("
			UPDATE users_info AS ui
				JOIN users_main AS um ON um.ID = ui.UserID
			SET ui.RatioWatchEnds = '0000-00-00 00:00:00',
				ui.RatioWatchDownload = '0',
				um.can_leech = '1'
			WHERE ui.UserID IN(".implode(',', $OffRatioWatch).')');
}

foreach ($OffRatioWatch as $UserID) {
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, ['RatioWatchEnds' => '0000-00-00 00:00:00', 'RatioWatchDownload' => '0', 'CanLeech' => 1]);
    $Cache->commit_transaction(0);
    Misc::send_pm($UserID, 0, "You have been taken off Ratio Watch", "Congratulations! Feel free to begin downloading again.\n To ensure that you do not get put on ratio watch again, please read the rules located [url=".site_url()."rules.php?p=ratio]here[/url].\n");
    echo "Ratio watch off: $UserID\n";
}
$DB->set_query_id($UserQuery);
$Passkeys = $DB->collect('torrent_pass');
foreach ($Passkeys as $Passkey) {
    Tracker::update_tracker('update_user', ['passkey' => $Passkey, 'can_leech' => '1']);
}

// Put user on ratio watch if he doesn't meet the standards
sleep(10);
$DB->query("
		SELECT m.ID, m.Downloaded
		FROM users_info AS i
			JOIN users_main AS m ON m.ID = i.UserID
		WHERE m.Downloaded > 0
			AND m.Uploaded / m.Downloaded < m.RequiredRatio
			AND i.RatioWatchEnds = '0000-00-00 00:00:00'
			AND m.Enabled = '1'
			AND m.can_leech = '1'");
$OnRatioWatch = $DB->collect('ID');

if (count($OnRatioWatch) > 0) {
    $DB->query("
			UPDATE users_info AS i
				JOIN users_main AS m ON m.ID = i.UserID
			SET i.RatioWatchEnds = '".time_plus(60 * 60 * 24 * 14)."',
				i.RatioWatchTimes = i.RatioWatchTimes + 1,
				i.RatioWatchDownload = m.Downloaded
			WHERE m.ID IN(".implode(',', $OnRatioWatch).')');
}

foreach ($OnRatioWatch as $UserID) {
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, ['RatioWatchEnds' => time_plus(60 * 60 * 24 * 14), 'RatioWatchDownload' => 0]);
    $Cache->commit_transaction(0);
    Misc::send_pm($UserID, 0, 'You have been put on Ratio Watch', "This happens when your ratio falls below the requirements we have outlined in the rules located [url=".site_url()."rules.php?p=ratio]here[/url].\n For information about ratio watch, click the link above.");
    echo "Ratio watch on: $UserID\n";
}

sleep(5);
