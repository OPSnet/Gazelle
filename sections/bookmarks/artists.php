<?php

$userMan = new Gazelle\Manager\User;
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
    if ($user->id() != $Viewer->id() && !$Viewer->permitted('users_override_paranoia')) {
        error(403);
    }
}

echo $Twig->render('bookmark/artist.twig', [
    'list'   => (new Gazelle\User\Bookmark($user))->artistList(),
    'user'   => $user,
    'viewer' => $Viewer,
]);
