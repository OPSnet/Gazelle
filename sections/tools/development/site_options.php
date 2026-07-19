<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('admin_manage_permissions', 'users_mod')) {
    Error403::error();
}

$manager = new Manager\SiteOption();

if ($Viewer->permitted('admin_manage_permissions') && isset($_POST['submit'])) {
    authorize();
    $manager->modifyOption(trim($_POST['name']), trim($_POST['value']));
}

echo $Twig->render('admin/site-option.twig', [
    'list'   => $manager->list(),
    'viewer' => $Viewer,
]);
