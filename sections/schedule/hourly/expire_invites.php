<?php

//------------- Expire invites ------------------------------------------//
sleep(3);
$DB->query("SELECT InviterID FROM invites WHERE Expires < '$sqltime'");
$Users = $DB->to_array();
foreach ($Users as $User) {
    list($UserID) = $User;
    $DB->prepared_query("UPDATE users_main SET Invites = Invites + 1 WHERE ID = ?", $UserID);
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, array('Invites' => '+1'));
    $Cache->commit_transaction(0);
}
$DB->query("DELETE FROM invites WHERE Expires < '$sqltime'");
