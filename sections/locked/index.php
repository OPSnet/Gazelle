<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod') && !$Viewer->isLocked()) {
    header('Location: /');
    return;
}

echo $Twig->render('user/locked.twig', [
    'viewer' => $Viewer,
]);
