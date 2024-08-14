<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_mod') && !$Viewer->isLocked()) {
    header('Location: /');
    exit;
}

echo $Twig->render('user/locked.twig', [
    'viewer' => $Viewer,
]);
