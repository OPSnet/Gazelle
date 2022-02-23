<?php

$userMan = new Gazelle\Manager\User;
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} elseif (!$Viewer->permitted('users_mod')) {
    error(403);
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}

echo $Twig->render('user/timeline.twig', [
    'user'   => $user,
    'charts' => (new Gazelle\Stats\User($user->id()))->timeline(),
    'viewer' => $Viewer,
]);
