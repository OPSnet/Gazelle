<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permittedAny('users_view_invites', 'users_disable_users', 'users_edit_invites', 'users_disable_any')) {
    error(403);
}

$doComment = false;
$doDisable = false;
$doInvites = false;
$message = null;

if (isset($_POST['id'])) {
    authorize();
    $comment   = trim($_POST['comment'] ?? '');
    $action    = $_POST['perform'] ?? '';
    $doDisable = $action === 'disable';
    $doInvites = $action === 'invites';
    if (!($comment || $doDisable || $doInvites)) {
        error("No maniplation action specified");
    }
    if (!$_POST['comment']) {
        error('Please enter a comment to add to the users affected.');
    }
    $userMan = new Gazelle\Manager\User();
    $id      = trim($_POST['id']);
    $user    = $userMan->find($id);
    if (is_null($user)) {
        error((int)$id
            ? "No such user '{$_POST['id']}'"
            : "No such user '{$_POST['id']}', did you mean '@{$_POST['id']}'?"
        );
    }

    $message = (new Gazelle\User\InviteTree($user))
        ->manipulate(
            $comment,
            $doDisable,
            $doInvites,
            new \Gazelle\Tracker(),
            $Viewer,
            $userMan,
        );
}

echo $Twig->render('user/invite-tree-bulkedit.twig', [
    'viewer'  => $Viewer,
    'message' => $message,
]);
