<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();
if (!isset($_GET['userid'])) {
    if (!$Viewer->permitted('site_user_stats')) {
        Error403::error();
    }
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
    if ($user->id != $Viewer->id && !$Viewer->permitted('users_mod')) {
        Error403::error();
    }
}

echo $Twig->render('user/timeline.twig', [
    'user'   => $user,
    'charts' => $user->stats()->timeline(),
    'viewer' => $Viewer,
]);
