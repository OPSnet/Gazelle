<?php

//------------- Expire invites ------------------------------------------//
sleep(3);
$DB->query("
		SELECT InviterID
		FROM invites
		WHERE Expires < '$sqltime'");
$Users = $DB->to_array();
foreach ($Users as $UserID) {
	list($UserID) = $UserID;
	$DB->query("
			SELECT Invites, PermissionID
			FROM users_main
			WHERE ID = $UserID");
	list($Invites, $PermID) = $DB->next_record();
	if (($Invites < 2 && $Classes[$PermID]['Level'] <= $Classes[POWER]['Level']) || ($Invites < 4 && $PermID == ELITE)) {
		$DB->query("
				UPDATE users_main
				SET Invites = Invites + 1
				WHERE ID = $UserID");
		$Cache->begin_transaction("user_info_heavy_$UserID");
		$Cache->update_row(false, array('Invites' => '+1'));
		$Cache->commit_transaction(0);
	}
}
$DB->query("
		DELETE FROM invites
		WHERE Expires < '$sqltime'");
