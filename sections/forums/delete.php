<?php
authorize();

if (!check_perms('site_forum_post_delete')) {
    error(403);
}
$postId = (int)($_GET['postid'] ?? 0);
$forum = (new Gazelle\Manager\Forum)->findByPostId($postId);
if (is_null($forum)) {
    error(404);
}

if (!$forum->removePost($postId)) {
    error(404);
}
