<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_invite_source')) {
    error(403);
}

$user = (new Gazelle\Manager\User())->find(trim($_POST['user'] ?? ''));
if ($user) {
    header('Location: ' . $user->location() . "#invite_source");
    exit;
}

echo $Twig->render('admin/invite-source.twig', [
    'list'   => (new Gazelle\Manager\InviteSource())->summaryByInviter(),
    'viewer' => $Viewer,
]);
