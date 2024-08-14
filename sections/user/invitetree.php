<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$userMan = new Gazelle\Manager\User();
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_view_invites')) {
        error(403);
    }
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}

echo $Twig->render('user/invite-tree-page.twig', [
    ...(new Gazelle\User\InviteTree($user, $userMan))->details($Viewer),
    'user'   => $user,
    'viewer' => $Viewer,
]);
