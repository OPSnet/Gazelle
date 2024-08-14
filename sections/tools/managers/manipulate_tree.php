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
    $user = $userMan->find(trim($_POST['id']));
    if (is_null($user)) {
        error(404);
    }

    $message = (new Gazelle\User\InviteTree($user, $userMan))
        ->manipulate(
            $comment,
            $doDisable,
            $doInvites,
            new \Gazelle\Tracker(),
            $Viewer
        );
}

echo $Twig->render('user/invite-tree-bulkedit.twig', [
    'auth'    => $Viewer->auth(),
    'message' => $message,
]);
