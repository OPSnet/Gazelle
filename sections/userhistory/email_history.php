<?php

if (!$Viewer->permitted('users_view_email')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

echo $Twig->render('user/email-history.twig', [
    'user' => $user,
]);
