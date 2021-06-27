<?php

authorize();

if (!check_perms('site_moderate_forums')) {
    error(403);
}

$threadId = (int)$_GET['threadid'];
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
$postId = (int)$_GET['postid'];
if (!$postId) {
    error(404);
}

$forum->stickyPost($Viewer->id(), $threadId, $postId, empty($_GET['remove']));

header('Location: forums.php?action=viewthread&threadid='.$threadId);
