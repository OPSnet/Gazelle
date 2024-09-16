<?php
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

if (!$Viewer->permitted('users_mod')) {
    error(403);
}
$userMan = new Gazelle\Manager\User();
$user = $userMan->findById((int)($_GET['id'] ?? 0));
if (is_null($user)) {
    error(404);
}
$user->auditTrail()->migrate($userMan);

echo $Twig->render('user/audit.twig', [
    'user' => $user,
]);
