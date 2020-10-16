<?php

if (!check_perms('users_view_invites')) {
    error(403);
}

$inviteMan = new Gazelle\Manager\Invite;

$removed = null;
if (!empty($_POST['invitekey']) && check_perms('users_edit_invites')) {
    authorize();
    $removed = $inviteMan->removeInviteKey($_POST['invitekey']);
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $inviteMan->setSearch($search);
}
$pending = $inviteMan->totalPending();
[$page, $limit] = Format::page_limit(INVITES_PER_PAGE);

View::show_header('Invite Pool');
echo G::$Twig->render('invite/pool.twig', [
    'auth'    => $LoggedUser['AuthKey'],
    'linkbox' => Format::get_pages($page, $pending, INVITES_PER_PAGE, 11),
    'list'    => $inviteMan->pendingInvites($limit),
    'page'    => $page,
    'pending' => $pending,
    'removed' => $removed,
    'search'  => $search,
    'title'   => 'Invite Pool',
    'can_edit_invites' => check_perms('users_edit_invites'),
]);
View::show_footer();
