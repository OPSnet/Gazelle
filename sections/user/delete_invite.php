<?php
authorize();

$InviteKey = trim($_GET['invite']);
$DB->prepared_query('
    SELECT InviterID
    FROM invites
    WHERE InviteKey = ?
    ', $InviteKey
);
list($UserID) = $DB->next_record();
if (!$DB->has_results() || $UserID != $LoggedUser['ID']) {
    error(404);
}

$DB->prepared_query('
    DELETE FROM invites
    WHERE InviteKey = ?
    ', $InviteKey
);

if (!check_perms('site_send_unlimited_invites')) {
    $DB->prepared_query('
        SELECT Invites
        FROM users_main
        WHERE ID = ?
        ', $UserID
    );
    list($Invites) = $DB->next_record();
    if ($Invites < 10) {
        $DB->prepared_query('
            UPDATE users_main
            SET Invites = Invites + 1
            WHERE ID = ?
            ', $UserID
        );
        $Cache->begin_transaction("user_info_heavy_$UserID");
        $Cache->update_row(false, ['Invites' => '+1']);
        $Cache->commit_transaction(0);
    }
}
header('Location: user.php?action=invite');
