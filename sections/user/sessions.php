<?php

if (!isset($_GET['userid'])) {
    $userId = $LoggedUser['ID'];
} elseif (!check_perms('users_view_ips') || !check_perms('users_logout')) {
        error(403);
} else {
    $userId = (int)$_GET['userid'];
}
$user = (new Gazelle\Manager\User)->findById($userId);
if (is_null($user)) {
    error(404);
}

$sessionMan = new Gazelle\Session($userId);
if (isset($_POST['all'])) {
    authorize();
    $sessionMan->dropAll();
}
if (isset($_POST['session'])) {
    authorize();
    $sessionMan->drop($_POST['session']);
}

View::show_header($user->username().' &rsaquo; Sessions');
echo $Twig->render('user/session.twig', [
    'auth'    => $LoggedUser['AuthKey'],
    'current' => $SessionID,
    'session' => $sessionMan->loadSessions(),
    'user'    => $user,
]);
View::show_footer();
