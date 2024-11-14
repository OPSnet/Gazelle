<?php
/** @phpstan-var \Gazelle\User $Viewer */

$user = (new Gazelle\Manager\User())->findById((int)($_REQUEST['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
if ($user->id() != $Viewer->id() && !$Viewer->permitted('users_mod')) {
    error(403);
}

switch ($_GET['do'] ?? '') {
    case 'configure':
        if ($user->TFAKey()) {
            error($Viewer->permitted('users_edit_password') ? '2FA is already configured' : 404);
        }
        include_once 'configure.php';
        break;

    case 'complete':
        include_once 'complete.php';
        break;

    case 'remove':
        include_once 'remove.php';
        break;

    default:
        error(404);
}
