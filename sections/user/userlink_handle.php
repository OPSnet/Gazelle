<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('users_linked_users')) {
    error(403);
}
authorize();

$userMan = new Gazelle\Manager\User();
$source  = $userMan->findById((int)$_REQUEST['userid']);
if (is_null($source)) {
    error(404);
}
$userLink = new Gazelle\User\UserLink($source);

switch ($_REQUEST['dupeaction'] ?? '') {
    case 'remove':
        $userLink->removeUser($userMan->findById($_REQUEST['removeid']), $Viewer);
        break;

    case 'update':
        $updateNote = isset($_REQUEST['update_note']);

        if ($_REQUEST['target']) {
            $username = trim($_REQUEST['target']);
            $target = $userMan->find($username);
            if (is_null($target)) {
                error("User '" . display_str($username) . "' not found.");
            } elseif ($source->id() === $target->id()) {
                error("Cannot link a user to themselves");
            }
            $userLink->dupe($target, $Viewer, $updateNote);
        }

        if ($_REQUEST['dupecomments']) {
            $userLink->addGroupComment($_REQUEST['dupecomments'], $Viewer, $updateNote);
        }
        break;

    default:
        error(403);
}

header("Location: {$source->location()}");
