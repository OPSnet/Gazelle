<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_linked_users')) {
    Error403::error();
}
authorize();

$userMan = new Manager\User();
$source  = $userMan->findById((int)$_REQUEST['userid']);
if (is_null($source)) {
    Error404::error();
}
$userLink = new User\UserLink($source);

switch ($_REQUEST['dupeaction'] ?? '') {
    case 'remove':
        $userLink->removeUser($userMan->findById((int)$_REQUEST['removeid']), $Viewer);
        break;

    case 'update':
        $updateNote = isset($_REQUEST['update_note']);

        if ($_REQUEST['target']) {
            $username = trim($_REQUEST['target']);
            $target = $userMan->find($username);
            if (is_null($target)) {
                Error400::error("User '" . display_str($username) . "' not found.");
            } elseif ($source->id === $target->id) {
                Error400::error("Cannot link a user to themselves");
            }
            $userLink->dupe($target, $Viewer, $updateNote);
        }

        if ($_REQUEST['dupecomments']) {
            $userLink->addGroupComment($_REQUEST['dupecomments'], $Viewer, $updateNote);
        }
        break;

    default:
        Error403::error();
}

header("Location: {$source->location()}");
