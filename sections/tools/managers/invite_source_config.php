<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_invite_source')) {
    Error403::error();
}

$manager = new Manager\InviteSource();
if (!empty($_POST['name'])) {
    authorize();
    $manager->create(trim($_POST['name']));
}
$remove = array_key_extract_suffix('remove-', $_POST);
if ($remove) {
    authorize();
    foreach ($remove as $r) {
        $manager->remove($r);
    }
}

echo $Twig->render('admin/invite-source-config.twig', [
    'list'   => $manager->usageList(),
    'viewer' => $Viewer,
]);
