<?php
authorize();

// Make sure they are moderators
if (!check_perms('site_admin_forums')) {
    error(403);
}

$postId = (int)$_GET['postid'];
if (!$postId) {
    error(404);
}

$forum = (new Gazelle\Manager\Forum)-findByPostId($postId);
if (!$forum->removePost($postId)) {
    error(404);
}
