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

$sessionMan = new Gazelle\Session($user->id());
if (isset($_POST['all'])) {
    authorize();
    $sessionMan->dropAll();
}
if (isset($_POST['session'])) {
    authorize();
    $sessionMan->drop($_POST['session']);
}

echo $Twig->render('user/session.twig', [
    'auth'    => $Viewer->auth(),
    'current' => $SessionID,
    'session' => $sessionMan->loadSessions(),
    'user'    => $user,
]);
