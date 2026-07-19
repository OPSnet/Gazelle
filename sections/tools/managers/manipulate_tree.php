<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('users_view_invites', 'users_disable_users', 'users_edit_invites', 'users_disable_any')) {
    Error403::error();
}

$doComment = false;
$doDisable = false;
$doInvites = false;
$message   = null;

if (isset($_POST['id'])) {
    authorize();
    $comment   = trim($_POST['comment'] ?? '');
    $action    = $_POST['perform'] ?? '';
    $doDisable = $action === 'disable';
    $doInvites = $action === 'invites';
    if (!($comment || $doDisable || $doInvites)) {
        Error400::error("No maniplation action specified");
    }
    if (!$_POST['comment']) {
        Error400::error('Please enter a comment to add to the users affected.');
    }
    $userMan = new Manager\User();
    $id      = trim($_POST['id']);
    $user    = $userMan->find($id);
    if (is_null($user)) {
        Error404::error((int)$id
            ? "No such user '{$_POST['id']}'"
            : "No such user '{$_POST['id']}', did you mean '@{$_POST['id']}'?"
        );
    }

    $message = new User\InviteTree($user)
        ->manipulate(
            $comment,
            $doDisable,
            $doInvites,
            new Tracker(),
            $Viewer,
            $userMan,
        );
}

echo $Twig->render('user/invite-tree-bulkedit.twig', [
    'message' => $message,
    'viewer'  => $Viewer,
]);
