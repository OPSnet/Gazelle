<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_permissions')) {
    Error403::error();
}

echo $Twig->render('admin/staff-group.twig', [
    'list'   => new Manager\StaffGroup()->groupList(),
    'viewer' => $Viewer,
]);
