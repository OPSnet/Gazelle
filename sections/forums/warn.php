<?php

if (!$Viewer->permitted('users_warn')) {
    error(403);
}

$post = (new Gazelle\Manager\ForumPost())->findById((int)($_POST['postid'] ?? 0));
if (is_null($post)) {
    error(404);
}
$user = (new Gazelle\Manager\User())->findById((int)($_POST['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}

echo $Twig->render('forum/warn.twig', [
    'auth'     => $Viewer->auth(),
    'post'     => $post,
    'user'     => $user,
]);
