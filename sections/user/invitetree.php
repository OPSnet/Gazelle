<?php

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_view_invites')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}

echo $Twig->render('user/invite-tree-page.twig', array_merge(
    ['user' => $user],
    (new Gazelle\InviteTree($user->id()))->details(),
));
