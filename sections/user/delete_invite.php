<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$inviteKey = trim($_GET['invite'] ?? '');
$user = new Manager\Invite()->findUserByKey($inviteKey, new Manager\User());
if (is_null($user)) {
    Error404::error();
}
if ($user->id !== $Viewer->id) {
    Error403::error();
}

$user->invite()->revoke($inviteKey);
header('Location: user.php?action=invite');
