<?php

if (!check_perms('users_view_email')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

View::show_header($user->username() . " &rasquo; Email History");
echo $Twig->render('user/email-history.twig', [
    'user' => $user,
]);
View::show_footer();
