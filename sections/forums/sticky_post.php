<?php

enforce_login();
authorize();

if (!check_perms('site_moderate_forums')) {
    error(403);
}

$threadId = (int)$_GET['threadid'];
$postId   = (int)$_GET['postid'];
if (!$threadId || !$postId) {
    error(404);
}

$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
$forum->stickyPost($LoggedUser['ID'], $threadId, $postId, empty($_GET['remove']));

header('Location: forums.php?action=viewthread&threadid='.$threadId);
