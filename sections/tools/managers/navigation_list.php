<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_navigation')) {
    error(403);
}

echo $Twig->render('admin/user-navigation.twig', [
    'list'   => (new Gazelle\Manager\UserNavigation())->fullList(),
    'viewer' => $Viewer,
]);
