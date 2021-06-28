<?php

if (!check_perms('admin_manage_permissions')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$user = $userMan->findById((int)($_REQUEST['userid']));
if (is_null($user)) {
    error(404);
}
$userId = $user->id();

if (isset($_POST['action'])) {
    authorize();
    $user->modifyPermissionList(
        array_filter($_POST, function ($p) {return strpos($p, 'perm_') === 0;}, ARRAY_FILTER_USE_KEY)
    );
}

View::show_header($user->username() . " &rsaquo; Permissions");
echo $Twig->render('user/privilege-list.twig', [
    'auth'    => $Viewer->auth(),
    'user'    => $user,
]);
View::show_footer();
