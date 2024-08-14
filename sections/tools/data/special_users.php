<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

echo $Twig->render('admin/user-custom-permission.twig', [
    'list' => (new Gazelle\Manager\User())->findAllByCustomPermission()
]);
