<?php

if (!$Viewer->permitted('users_view_invites')) {
    error(403);
}

$inviteMan = new Gazelle\Manager\Invite;

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

$paginator = new Gazelle\Util\Paginator(INVITES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($inviteMan->totalPending());

echo $Twig->render('invite/pool.twig', [
    'auth'      => $Viewer->auth(),
    'paginator' => $paginator,
    'list'      => $inviteMan->pendingInvites($paginator->limit(), $paginator->offset()),
    'pending'   => $pending,
    'removed'   => $removed,
    'search'    => $search,
    'can_edit'  => $Viewer->permitted('users_edit_invites'),
]);
