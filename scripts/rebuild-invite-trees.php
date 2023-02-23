<?php

require_once(__DIR__ . '/../lib/bootstrap.php');
$db = Gazelle\DB::DB();

$db->prepared_query("
    DELETE FROM invite_tree
");
$invite = $db->prepared_query('
	SELECT UserID, Inviter
    FROM users_info
    WHERE Inviter IS NOT NULL
    ORDER BY UserID
');
$inv = [];
while ([$invitee, $inviter] = $db->next_record()) {
    $save = $db->get_query_id();
    if (!isset($inv[$inviter])) {
        $inv[$inviter] = new Gazelle\User\InviteTree(new Gazelle\User($inviter));
    }
    $inv[$inviter]->add($invitee);
    $db->set_query_id($save);
}
