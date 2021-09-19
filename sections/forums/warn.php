<?php

if (!$Viewer->permitted('users_warn')) {
    error(403);
}

$postId = (int)$_POST['postid'];
$forum = (new Gazelle\Manager\Forum)->findByPostId($postId);
if (is_null($forum)) {
    error(404);
}
$user = (new Gazelle\Manager\User)->findById((int)$_POST['userid']);
if (is_null($user)) {
    error(404);
}

echo $Twig->render('forum/warn.twig', [
    'auth'     => $Viewer->auth(),
    'body'     => $forum->postBody($postId),
    'forum_id' => $forum->id(),
    'post_id'  => $postId,
    'user'     => $user,
]);
