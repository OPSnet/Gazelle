<?php

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_view_ips') || !$Viewer->permitted('users_logout')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
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
