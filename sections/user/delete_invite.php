<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$inviteKey = trim($_GET['invite'] ?? '');
$user = (new Gazelle\Manager\Invite())->findUserByKey($inviteKey, new Gazelle\Manager\User());
if (is_null($user)) {
    error(404);
}
if ($user->id() != $Viewer->id()) {
    error(403);
}

$user->invite()->revoke($inviteKey);
header('Location: user.php?action=invite');
