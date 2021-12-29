<?php

require_once(__DIR__ . '/../lib/bootstrap.php');

$DB->prepared_query("
    DELETE FROM invite_tree
");
$invite = $DB->prepared_query('
	SELECT UserID, Inviter
    FROM users_info
    WHERE Inviter IS NOT NULL
    ORDER BY UserID
');
$inv = [];
while ([$invitee, $inviter] = $DB->next_record()) {
    $save = $DB->get_query_id();
    if (!isset($inv[$inviter])) {
        $inv[$inviter] = new Gazelle\InviteTree($inviter);
    }
    $inv[$inviter]->add($invitee);
    $DB->set_query_id($save);
}
