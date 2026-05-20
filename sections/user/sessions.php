<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!isset($_GET['id'])) {
    $user = $Viewer;
} else {
    $userId = (int)$_GET['id'];
    if ($userId !== $Viewer->id() && !$Viewer->permittedAny('users_logout', 'users_view_ips')) {
        Error403::error();
    }
    $user = new Manager\User()->findById($userId);
    if (is_null($user)) {
        Error404::error();
    }
}

$session = new User\Session($user);
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
    'current' => $session->requestContext()->sessionId(),
    'session' => $session->info(),
    'user'    => $user,
    'viewer'  => $Viewer,
]);
