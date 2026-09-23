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

$privMan = new Manager\Privilege();

$privilege = null;
if (isset($_REQUEST['id']) && $_REQUEST['id'] !== 'new') {
    $privilege = $privMan->findById((int)$_REQUEST['id']);
    if (is_null($privilege)) {
        header("Location: tools.php?action=userclass");
        return;
    }
}

echo $Twig->render('admin/privilege-edit.twig', [
    'edited'     => isset($usersAffected),
    'edit_total' => $usersAffected ?? 0,
    'group_list' => new Manager\StaffGroup()->groupList(),
    'privilege'  => $privilege,
    'viewer'     => $Viewer,
]);
