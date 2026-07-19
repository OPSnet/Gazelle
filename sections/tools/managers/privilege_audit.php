<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_permissions')) {
    Error403::error();
}

$privilegeManager = new Manager\Privilege();
if (isset($_GET['flush'])) {
    $privilegeManager->flush();
}

echo $Twig->render('admin/privilege-audit.twig', [
    'privilege_manager' => $privilegeManager,
    'user_manager'      => new Manager\User(),
    'config'            => USERCLASS_AUDIT,
]);
