<?php
authorize();

$inviteKey = trim($_GET['invite']);
$userId = $DB->scalar("
    SELECT InviterID FROM invites WHERE InviteKey = ?
    ", $inviteKey
);
if (is_null($userId)) {
    error(404);
}
if ($userId != $Viewer->id()) {
    error(403);
}

(new Gazelle\User($userId))->removeInvite($inviteKey);
header('Location: user.php?action=invite');
