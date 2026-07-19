<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_dnu')) {
    Error403::error();
}

echo $Twig->render('admin/dnu.twig', [
    'list'   => new Manager\DNU()->dnuList(),
    'viewer' => $Viewer,
]);
