<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_permissions')) {
    Error403::error();
}

if (isset($_REQUEST['id']) && $_REQUEST['id'] === 'new') {
    require __DIR__ . '/userclass_edit.php';
    exit;
}

echo $Twig->render('admin/privilege-usage.twig', [
    'list'   => new Manager\Privilege()->usageList(),
    'viewer' => $Viewer,
]);
