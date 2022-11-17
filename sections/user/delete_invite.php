<?php

authorize();

$inviteKey = trim($_GET['invite'] ?? '');
$user = (new Gazelle\Manager\Invite)->findUserByKey($inviteKey, new Gazelle\Manager\User);
if (is_null($user)) {
    error(404);
}
if ($user->id() != $Viewer->id()) {
    error(403);
}

$user->removeInvite($inviteKey);
header('Location: user.php?action=invite');
