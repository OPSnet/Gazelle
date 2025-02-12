<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_whitelist')) {
    error(403);
}

echo $Twig->render('admin/client-whitelist.twig', [
    'list'   => (new Gazelle\Manager\ClientWhitelist())->list(),
    'viewer' => $Viewer,
]);
