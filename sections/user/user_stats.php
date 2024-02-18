<?php

$userMan = new Gazelle\Manager\User();
if (!isset($_GET['userid'])) {
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
