<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_type=1);

authorize();

header('Content-Type: application/json; charset=utf-8');

if (!$Viewer->permitted('users_view_invites')) {
    json_die("Forbidden");
}
$userMan = new Gazelle\Manager\User();
$user    = $userMan->findById((int)($_POST['id']));
if (is_null($user)) {
    json_die("Not found");
}

$tree = new Gazelle\User\InviteTree($user, $userMan);
echo json_encode($Twig->render('user/invite-tree.twig', [
    ...$tree->details($Viewer),
    'user'   => $user,
    'viewer' => $Viewer,
]));
