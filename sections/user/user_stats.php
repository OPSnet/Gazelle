<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$userMan = new Gazelle\Manager\User();
if (!isset($_GET['userid'])) {
    if (!$Viewer->permitted('site_user_stats')) {
        error(403);
    }
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
    if ($user->id() != $Viewer->id() && !$Viewer->permitted('users_mod')) {
        error(403);
    }
}

echo $Twig->render('user/timeline.twig', [
    'user'   => $user,
    'charts' => $user->stats()->timeline(),
    'viewer' => $Viewer,
]);
