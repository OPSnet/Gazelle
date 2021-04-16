<?php

if (!check_perms('users_view_keys')) {
    error(403);
}
$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}

View::show_header($user->username() . ' &rsaquo; Announce Key History');
echo G::$Twig->render('admin/announcekey-history.twig', [
    'user' => $user,
]);
View::show_footer();
