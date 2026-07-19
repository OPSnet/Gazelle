<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_site_debug')) {
    Error403::error();
}

echo $Twig->render('admin/privilege-matrix.twig', [
    'class_list' => new Manager\User()->classList(),
    'privilege'  => new Manager\Privilege()->privilege(),
]);
