<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$user = new Manager\User()->findById((int)$_GET['userid']);
if (is_null($user)) {
    Error403::error();
}
if (!$Viewer->permitted('users_mod') && $user->id !== $Viewer->id) {
    Error403::error();
}
authorize();

new Manager\Notification()->push([$user->id],
    'Push!', 'You have been pushed by ' . $Viewer->username());

header('Location: ' . $user->location() . '&action=edit');
