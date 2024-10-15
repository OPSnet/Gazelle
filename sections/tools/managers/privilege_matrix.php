<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
}

echo $Twig->render('admin/privilege-matrix.twig', [
    'class_list' => (new Gazelle\Manager\User())->classList(),
    'privilege'  => (new Gazelle\Manager\Privilege())->privilege(),
]);
