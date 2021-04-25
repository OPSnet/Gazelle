<?php
/************************************************************************
||------------|| Password reset history page ||------------------------||

This page lists password reset IP and Times a user has made on the site.
It gets called if $_GET['action'] == 'password'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

if (!check_perms('users_view_keys')) {
    error(403);
}
$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

View::show_header($user->username() . " &rsaquo; Password reset history");
echo $Twig->render('user/password-history.twig', [
    'list' => $user->passwordHistory(),
    'user' => $user,
]);
View::show_footer();
