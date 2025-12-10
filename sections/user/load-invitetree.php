<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

authorize();

header('Content-Type: application/json');

if (!$Viewer->permitted('users_view_invites')) {
    json_die("Forbidden");
}
$userMan = new Manager\User();
$user    = $userMan->findById((int)($_POST['id']));
if (is_null($user)) {
    json_die("Not found");
}

echo json_encode($Twig->render('user/invite-tree.twig', [
    'tree'   => new User\InviteTree($user),
    'user'   => $user,
    'viewer' => $Viewer,
]));
