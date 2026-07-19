<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_admin_requests')) {
    Error403::error();
}

$request = new Manager\Request()->findById((int)$_GET['id']);
if (is_null($request)) {
    Error404::error();
}

echo $Twig->render('request/edit-bounty.twig', [
    'request' => $request,
    'viewer'  => $Viewer,
]);
