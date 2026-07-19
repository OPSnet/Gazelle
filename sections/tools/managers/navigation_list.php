<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_navigation')) {
    Error403::error();
}

echo $Twig->render('admin/user-navigation.twig', [
    'list'   => new Manager\UserNavigation()->fullList(),
    'viewer' => $Viewer,
]);
