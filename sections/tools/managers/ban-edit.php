<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_ipbans')) {
    Error403::error();
}

$manager = new Manager\Ban();
$ban = $manager->findById((int)($_GET['id'] ?? 0));
if (is_null($ban)) {
    Error404::error("No such id " . html_escape($_GET['id']) . ".");
}

echo $Twig->render('admin/ip-ban-edit.twig', [
    'ban'    => $ban,
    'viewer' => $Viewer,
]);
