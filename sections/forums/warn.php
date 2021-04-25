<?php

if (!check_perms('users_warn')) {
    error(404);
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

View::show_header('Warn User');
echo $Twig->render('forum/warn.twig', [
    'auth'     => $LoggedUser['AuthKey'],
    'body'     => $forum->postBody($postId),
    'forum_id' => $forum->id(),
    'post_id'  => $postId,
    'user'     => $user,
]);
View::show_footer();
