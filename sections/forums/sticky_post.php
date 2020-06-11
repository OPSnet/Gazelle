<?php

enforce_login();
authorize();

if (!check_perms('site_moderate_forums')) {
    error(403);
}

$threadId = (int)$_GET['threadid'];
$postId   = (int)$_GET['postid'];
if ($threadId < 1 || $postId < 1) {
    error(404);
}

$forum = new \Gazelle\Forum;
$forum->stickyPost($LoggedUser['ID'], $threadId, $postId, empty($_GET['remove']));

header('Location: forums.php?action=viewthread&threadid='.$threadId);
