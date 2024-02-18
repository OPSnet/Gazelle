<?php

if (!$Viewer->permitted('users_view_keys')) {
    error(403);
}

$user = (new Gazelle\Manager\User())->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

echo $Twig->render('user/password-history.twig', [
    'list' => $user->passwordHistory(),
    'user' => $user,
]);
