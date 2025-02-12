<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_dnu')) {
    error(403);
}

echo $Twig->render('admin/dnu.twig', [
    'list'   => (new Gazelle\Manager\DNU())->dnuList(),
    'viewer' => $Viewer,
]);
