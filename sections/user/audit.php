<?php

/**
 * @phpstan-var \Twig\Environment $Twig
 * @phpstan-var \Gazelle\User $Viewer
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('admin_audit_edit', 'admin_audit_view')) {
    Error403::error();
}
$userMan = new Manager\User();
$user = $userMan->findById((int)($_GET['id'] ?? 0));
if (is_null($user)) {
    Error404::error();
}

echo $Twig->render('user/audit.twig', [
    'edit'   => isset($_GET['edit']),
    'user'   => $user,
    'viewer' => $Viewer,
]);
