<?php

if (!isset($_GET['userid'])) {
    $userId = $LoggedUser['ID'];
} else {
    if (!check_perms('users_view_invites')) {
        error(403);
    }
    $userId = (int)$_GET['userid'];
}
$user = (new Gazelle\Manager\User)->find($userId);
if (is_null($user)) {
    error(404);
}

View::show_header($user->username() . ' &rsaquo; Invites &rsaquo; Tree');
echo $Twig->render('user/invite-tree.twig', array_merge(
    ['user' => $user],
    (new Gazelle\InviteTree($userId))->details(),
));
View::show_footer();
