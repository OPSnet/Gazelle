<?php

if (!isset($_GET['userid'])) {
    $userId = $Viewer->id();
} else {
    if (!$Viewer->permitted('users_view_invites')) {
        error(403);
    }
    $userId = (int)$_GET['userid'];
}
$user = (new Gazelle\Manager\User)->find($userId);
if (is_null($user)) {
    error(404);
}

echo $Twig->render('user/invite-tree.twig', array_merge(
    ['user' => $user],
    (new Gazelle\InviteTree($userId))->details(),
));
