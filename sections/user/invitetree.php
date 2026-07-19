<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_view_invites')) {
        Error403::error();
    }
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
}

echo $Twig->render('user/invite-tree-page.twig', [
    'tree'   => new User\InviteTree($user),
    'user'   => $user,
    'viewer' => $Viewer,
]);
