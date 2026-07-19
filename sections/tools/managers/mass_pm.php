<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted("admin_global_notification")) {
    Error404::error();
}

echo $Twig->render('admin/mass-pm.twig', [
    'body'   => new Util\Textarea('body', '', 95, 10),
    'class'  => new Manager\User()->classList(),
    'viewer' => $Viewer,
]);
