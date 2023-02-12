<?php

if (!isset($_GET['id'])) {
    $user = $Viewer;
} else {
    $userId = (int)$_GET['id'];
    if ($userId !== $Viewer->id() && !$Viewer->permittedAny('users_logout', 'users_view_ips')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById($userId);
    if (is_null($user)) {
        error(404);
    }
}

$session = new Gazelle\User\Session($user);
if (isset($_POST['all'])) {
    authorize();
    $session->dropAll();
    header('Location: /');
    exit;
}
if (isset($_POST['session'])) {
    authorize();
    $session->drop($_POST['session']);
}

echo $Twig->render('user/session.twig', [
    'auth'    => $Viewer->auth(),
    'current' => $SessionID,
    'session' => $session->info(),
    'user'    => $user,
]);
