<?php

if (empty($_GET['do'])) {
    error(404);
}

$user = (new Gazelle\Manager\User)->findById((int)($_REQUEST['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
$userId = $user->id();
if ($userId != $Viewer->id() && !check_perms('users_mod')) {
    error(403);
}

switch($_GET['do']) {
    case 'configure':
        if ($user->TFAKey()) {
            error(check_perms('users_edit_password') ? '2FA is already configured' : 404);
        }
        require_once('configure.php');
        break;

    case 'complete':
        if ($user->TFAKey()) {
            error(check_perms('users_edit_password') ? '2FA is already configured' : 404);
        }
        require_once('complete.php');
        break;

    case 'remove':
        if (!$user->TFAKey()) {
            error(check_perms('users_edit_password') ? 'No 2FA configured' : 404);
        }
        require_once('remove.php');
        break;

    default:
        error(404);
        break;
}
