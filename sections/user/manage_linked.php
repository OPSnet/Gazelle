<?php

authorize();

if (!check_perms('users_edit_usernames')) {
    error(403);
}
$userId = (int)$_REQUEST['userid'];
if (!$userId) {
    error(404);
}
$source = new Gazelle\User($userId);
$userLink = new Gazelle\Manager\UserLink($source);

switch ($_REQUEST['dupeaction'] ?? '') {
    case 'remove':
        $userLink->remove(new Gazelle\User($_REQUEST['removeid']), $Viewer->username());
        break;

    case 'update':
        $updateNote = isset($_REQUEST['update_note']);

        if ($_REQUEST['target']) {
            $username = trim($_REQUEST['target']);
            $target = (new Gazelle\Manager\User)->findByUsername($username);
            if (is_null($target)) {
                error("User '" . display_str($username) . "' not found.");
            } elseif ($source->id() === $target->id()) {
                error("Cannot link a user to themselves");
            }
            $userLink->link($target, $Viewer->username(), $updateNote);
        }

        if ($_REQUEST['dupecomments']) {
            $userLink->addGroupComments($_REQUEST['dupecomments'], $Viewer->username(), $updateNote);
        }
        break;

    default:
        error(403);
}

header("Location: user.php?id={$userId}");
