<?php

$user = (new Gazelle\Manager\User)->findById((int)($_REQUEST['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
if ($user->id() != $Viewer->id() && !$Viewer->permitted('users_mod')) {
    error(403);
}

switch($_GET['do'] ?? '') {
    case 'configure':
        if ($user->TFAKey()) {
            error(check_perms('users_edit_password') ? '2FA is already configured' : 404);
        }
        require_once('configure.php');
        break;

    case 'complete':
        require_once('complete.php');
        break;

    case 'remove':
        require_once('remove.php');
        break;

    default:
        error(404);
        break;
}
