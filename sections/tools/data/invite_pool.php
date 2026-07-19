<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_invites')) {
    Error403::error();
}

$inviteMan = new Manager\Invite();

$removed = null;
if (!empty($_POST['invitekey']) && $Viewer->permitted('users_edit_invites')) {
    authorize();
    $removed = $inviteMan->removeInviteKey($_POST['invitekey']);
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $inviteMan->setSearch($search);
}
$pending = $inviteMan->totalPending();

$paginator = new Util\Paginator(INVITES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($pending);

echo $Twig->render('invite/pool.twig', [
    'paginator' => $paginator,
    'list'      => $inviteMan->pendingInvites($paginator->limit(), $paginator->offset()),
    'pending'   => $pending,
    'removed'   => $removed,
    'search'    => $search,
    'viewer'    => $Viewer,
]);
