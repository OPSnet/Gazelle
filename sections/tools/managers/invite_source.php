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

$user = new Manager\User()->find(trim($_POST['user'] ?? ''));
if ($user) {
    header("Location: {$user->location()}#invite_source");
    return;
}

echo $Twig->render('admin/invite-source.twig', [
    'list'   => new Manager\InviteSource()->summaryByInviter(),
    'viewer' => $Viewer,
]);
